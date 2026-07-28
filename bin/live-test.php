#!/usr/bin/env php
<?php

/**
 * Live smoke test for the Pecotamic Antispam addon.
 *
 * Exercises a running Statamic site end-to-end and verifies that the timing
 * cookie mechanism accepts a genuine submission and silently rejects the
 * spam-like ones (submitted too fast / without the timing cookie).
 *
 * Detection is black-box over HTTP — no filesystem or Control Panel access
 * required (see classify()).
 *
 * Usage:
 *   php bin/live-test.php        (interactive)
 *   composer live-test
 *
 * Answers can also be piped in prompt order:
 *   printf 'https://site.test/\ncontact\n_ptas\n5\nhoneypot\n' | php bin/live-test.php
 */

error_reporting(E_ALL & ~E_DEPRECATED);

const C_RESET = "\033[0m";
const C_RED = "\033[31m";
const C_GREEN = "\033[32m";
const C_YELLOW = "\033[33m";
const C_CYAN = "\033[36m";
const C_DIM = "\033[2m";

function out(string $text = ''): void
{
    fwrite(STDOUT, $text.PHP_EOL);
}

function ask(string $label, ?string $default = null): string
{
    $suffix = $default !== null ? C_DIM." [$default]".C_RESET : '';
    fwrite(STDOUT, $label.$suffix.': ');

    if (function_exists('posix_isatty') && posix_isatty(STDIN) && function_exists('readline')) {
        $line = readline('');
    } else {
        $line = fgets(STDIN);
    }

    $line = trim((string) $line);

    return $line === '' ? (string) $default : $line;
}

/**
 * Perform an HTTP request and return status, parsed JSON and any Set-Cookies.
 */
function httpRequest(string $method, string $url, array $cookies = [], array $post = [], bool $ajax = false): array
{
    $setCookies = [];

    $headers = ['Accept: text/html,application/json'];
    if ($cookies) {
        $headers[] = 'Cookie: '.implode('; ', array_map(
            fn ($v, $k) => $k.'='.$v,
            $cookies,
            array_keys($cookies)
        ));
    }
    if ($ajax) {
        $headers[] = 'X-Requested-With: XMLHttpRequest';
        $headers[] = 'Accept: application/json';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => $method === 'GET',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$setCookies) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                $pair = trim(substr($header, strlen('Set-Cookie:')));
                $pair = explode(';', $pair, 2)[0];
                [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $setCookies[trim($name)] = trim($value);
            }

            return strlen($header);
        },
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, buildQuery($post));
    }

    $body = curl_exec($ch);
    if ($body === false) {
        fwrite(STDERR, C_RED.'HTTP error: '.curl_error($ch).C_RESET.PHP_EOL);
        exit(1);
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $json = json_decode((string) $body, true);

    return [
        'status' => $status,
        'body' => (string) $body,
        'json' => is_array($json) ? $json : null,
        'cookies' => $setCookies,
    ];
}

/**
 * Build an urlencoded body from a list of [name, value] pairs (supports repeats).
 */
function buildQuery(array $pairs): string
{
    return implode('&', array_map(
        fn ($p) => rawurlencode($p[0]).'='.rawurlencode($p[1]),
        $pairs
    ));
}

/**
 * Extract the Statamic form handles referenced on a page.
 *
 * @return string[]
 */
function discoverHandles(string $html): array
{
    preg_match_all('#/!/forms/([A-Za-z0-9_-]+)#', $html, $m);

    return array_values(array_unique($m[1]));
}

/**
 * Parse the form for the given handle and return its action and a filled body.
 *
 * @return array{action:string,pairs:array<int,array{0:string,1:string}>}
 */
function parseForm(string $html, string $handle, string $honeypot): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    $form = null;
    foreach ($dom->getElementsByTagName('form') as $node) {
        if (str_contains((string) $node->getAttribute('action'), '/!/forms/'.$handle)) {
            $form = $node;
            break;
        }
    }

    if (! $form) {
        fwrite(STDERR, C_RED."No form found for handle '$handle'.".C_RESET.PHP_EOL);
        exit(1);
    }

    $action = $form->getAttribute('action');
    $pairs = [];

    $collect = function (string $tag) use ($form) {
        $nodes = [];
        foreach ($form->getElementsByTagName($tag) as $node) {
            $nodes[] = $node;
        }

        return $nodes;
    };

    foreach ($collect('input') as $input) {
        $name = $input->getAttribute('name');
        if ($name === '' || $name === $honeypot) {
            continue;
        }

        $type = strtolower($input->getAttribute('type') ?: 'text');
        $value = $input->getAttribute('value');

        switch ($type) {
            case 'submit':
            case 'button':
            case 'image':
            case 'file':
            case 'reset':
                break;
            case 'hidden':
                if ($value !== '') {
                    $pairs[] = [$name, $value];
                }
                break;
            case 'checkbox':
            case 'radio':
                $pairs[] = [$name, $value !== '' ? $value : 'on'];
                break;
            default:
                $pairs[] = [$name, sampleValue($type, $name)];
        }
    }

    foreach ($collect('textarea') as $textarea) {
        if ($name = $textarea->getAttribute('name')) {
            if ($name !== $honeypot) {
                $pairs[] = [$name, 'Antispam live test — please ignore.'];
            }
        }
    }

    foreach ($collect('select') as $select) {
        $name = $select->getAttribute('name');
        if ($name === '' || $name === $honeypot) {
            continue;
        }
        $chosen = '';
        foreach ($select->getElementsByTagName('option') as $option) {
            if (($v = $option->getAttribute('value')) !== '') {
                $chosen = $v;
                break;
            }
        }
        $pairs[] = [$name, $chosen];
    }

    return ['action' => $action, 'pairs' => $pairs];
}

function sampleValue(string $type, string $name): string
{
    return match ($type) {
        'email' => 'antispam-livetest@example.com',
        'tel' => '+49 30 0000000',
        'number', 'range' => '1',
        'url' => 'https://example.com',
        'date' => date('Y-m-d'),
        default => 'Antispam live test',
    };
}

/**
 * Classify a form response into an outcome.
 *
 * The FormController runs request validation first (4xx on failure), then the
 * honeypot and the FormSubmitted event (both answer 200), then saves and sends
 * mail. So a 5xx can only occur once the submission already passed the filter.
 */
function classify(array $response): string
{
    $status = $response['status'];

    if ($status === 419) {
        return 'csrf';
    }
    if ($status >= 400 && $status < 500) {
        return 'validation';
    }
    if ($status >= 500) {
        return 'passed_then_errored';
    }
    if (! is_array($response['json']) || ! array_key_exists('submission_created', $response['json'])) {
        return 'unknown';
    }

    // For an AJAX request Statamic's FormController answers 200 either way, but
    // reports a silent failure (a rejected submission) as submission_created:false
    // and a stored submission as submission_created:true.
    return $response['json']['submission_created'] ? 'accepted' : 'rejected';
}

/**
 * Reduce an outcome to the antispam filter's verdict, or 'inconclusive' when
 * the request never reached the filter cleanly (bad test data, CSRF, …).
 */
function filterVerdict(string $outcome): string
{
    return match ($outcome) {
        'accepted', 'passed_then_errored' => 'accepted',
        'rejected' => 'rejected',
        default => 'inconclusive',
    };
}

// ---------------------------------------------------------------------------
// Interactive setup
// ---------------------------------------------------------------------------

out(C_CYAN.'Pecotamic Antispam — live smoke test'.C_RESET);
out(C_DIM.'Verifies that genuine submissions pass and spam-like ones are silently rejected.'.C_RESET);
out();

$pageUrl = ask('Page URL that renders the form');
if ($pageUrl === '') {
    fwrite(STDERR, C_RED.'A page URL is required.'.C_RESET.PHP_EOL);
    exit(1);
}

$discovery = httpRequest('GET', $pageUrl);
if ($discovery['status'] >= 400) {
    fwrite(STDERR, C_RED."GET $pageUrl returned HTTP {$discovery['status']}.".C_RESET.PHP_EOL);
    exit(1);
}

$handles = discoverHandles($discovery['body']);
if (! $handles) {
    fwrite(STDERR, C_RED.'No Statamic form (/!/forms/…) found on that page.'.C_RESET.PHP_EOL);
    exit(1);
}
out(C_DIM.'Found form handles: '.implode(', ', $handles).C_RESET);

$handle = ask('Form handle', $handles[0]);
$cookieName = ask('Timing cookie name', '_ptas');
$minFill = (int) ask('minimum_fill_time (seconds)', '5');
$honeypot = ask('Honeypot field name (left empty)', 'honeypot');
out();
out(C_YELLOW.'Note: the genuine-submission scenario really submits the form — it stores a'.C_RESET);
out(C_YELLOW.'submission and triggers the notification email. Use a local/staging site.'.C_RESET);
out();

// ---------------------------------------------------------------------------
// Scenario runner
// ---------------------------------------------------------------------------

/**
 * Run a single scenario: fresh GET, optional cookie/timing manipulation, POST.
 */
function runScenario(string $pageUrl, string $handle, string $honeypot, string $cookieName, int $sleep, bool $dropCookie): array
{
    $get = httpRequest('GET', $pageUrl);
    $cookies = $get['cookies'];

    $hasTimingCookie = array_key_exists($cookieName, $cookies);

    if ($dropCookie) {
        unset($cookies[$cookieName]);
    }

    $form = parseForm($get['body'], $handle, $honeypot);

    if ($sleep > 0) {
        sleep($sleep);
    }

    $post = httpRequest('POST', $form['action'], $cookies, $form['pairs'], true);

    return [
        'outcome' => classify($post),
        'timingCookieIssued' => $hasTimingCookie,
        'response' => $post,
    ];
}

$scenarios = [
    'legit' => [
        'title' => 'Genuine submission (waited '.($minFill + 2).'s, cookie present)',
        'sleep' => $minFill + 2,
        'dropCookie' => false,
        'expect' => 'accepted',
    ],
    'too_fast' => [
        'title' => 'Submitted immediately (< minimum_fill_time)',
        'sleep' => 0,
        'dropCookie' => false,
        'expect' => 'rejected',
    ],
    'no_cookie' => [
        'title' => 'Submitted without the timing cookie',
        'sleep' => 0,
        'dropCookie' => true,
        'expect' => 'rejected',
    ],
];

$results = [];
$allPassed = true;

foreach ($scenarios as $key => $scenario) {
    out(C_CYAN.'▶ '.$scenario['title'].C_RESET);

    $result = runScenario($pageUrl, $handle, $honeypot, $cookieName, $scenario['sleep'], $scenario['dropCookie']);
    $outcome = $result['outcome'];
    $status = $result['response']['status'];
    $verdict = filterVerdict($outcome);
    $passed = $verdict === $scenario['expect'];
    $allPassed = $allPassed && $passed;
    $results[$key] = ['scenario' => $scenario, 'verdict' => $verdict, 'passed' => $passed, 'result' => $result];

    if (! $result['timingCookieIssued']) {
        out(C_YELLOW."  ⚠ No '$cookieName' cookie was issued on GET — is static caching serving this page? See README.".C_RESET);
    }

    $label = match ($outcome) {
        'accepted' => C_GREEN.'accepted — submission stored'.C_RESET,
        'rejected' => C_YELLOW.'silently rejected by the filter'.C_RESET,
        'passed_then_errored' => C_GREEN.'passed the filter'.C_RESET.C_DIM." (request then failed with HTTP $status downstream)".C_RESET,
        'validation' => C_RED."validation error (HTTP $status) — test data rejected before the filter".C_RESET,
        'csrf' => C_RED.'CSRF failure (HTTP 419)'.C_RESET,
        default => C_RED."unexpected response (HTTP $status, not AJAX JSON)".C_RESET,
    };
    out('  outcome:  '.$label);
    out('  expected: filter '.$scenario['expect']);
    out('  '.($passed ? C_GREEN.'PASS'.C_RESET : C_RED.'FAIL'.C_RESET));

    if ($outcome === 'passed_then_errored') {
        $message = $result['response']['json']['message'] ?? '';
        out(C_DIM.'  Not an antispam issue — a step after the filter failed (usually mail delivery).'.C_RESET);
        if ($message !== '') {
            out(C_DIM.'  '.substr($message, 0, 200).C_RESET);
        }
    }

    if ($outcome === 'validation') {
        $errors = $result['response']['json']['errors'] ?? [];
        out(C_DIM.'  '.substr(json_encode($errors), 0, 300).C_RESET);
        out(C_DIM.'  Fill the required fields with valid values (adjust bin/live-test.php sampleValue()).'.C_RESET);
    }
    out();
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

out(C_CYAN.'Summary'.C_RESET);
foreach ($results as $key => $r) {
    $mark = $r['passed'] ? C_GREEN.'PASS'.C_RESET : C_RED.'FAIL'.C_RESET;
    out(sprintf('  %-4s  filter %-12s → %s', $mark, $r['verdict'], $r['scenario']['title']));
}
out();

if ($allPassed) {
    out(C_GREEN.'All scenarios behaved as expected.'.C_RESET);
    exit(0);
}

out(C_RED.'Some scenarios did not behave as expected — see notes above.'.C_RESET);
exit(1);