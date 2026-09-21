<?php

namespace Pecotamic\Antispam\Rules;

use Illuminate\Support\Str;

/**
 * Checks the plausibility of submitted email addresses.
 *
 * Values are recognised by their shape rather than by field name, so the rule
 * needs no per-site configuration to find the address — and catches one pasted
 * into a message body as well.
 *
 * The MX lookup is off by default on purpose: it puts a DNS round trip in the
 * request path, and a resolver outage would make every address look invalid
 * and take the whole form down with it. Enable it only where DNS is reliable
 * and preferably at a weight below the threshold, so a failed lookup alone
 * cannot reject a submission.
 */
class Email extends FieldRule
{
    public function handle(): string
    {
        return 'email';
    }

    protected function inspect(string $field, string $value): ?string
    {
        $value = trim($value);

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $domain = Str::lower(Str::afterLast($value, '@'));

        if ($this->disposableDomains()->contains($domain)) {
            return sprintf("field '%s' uses the throwaway mail domain '%s'", $field, $domain);
        }

        if ($this->option('check_mx', false) && !checkdnsrr($domain, 'MX')) {
            return sprintf("field '%s' uses '%s', which has no MX record", $field, $domain);
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function disposableDomains(): \Illuminate\Support\Collection
    {
        static $bundled = null;

        $bundled ??= collect(file(
            __DIR__.'/../../resources/disposable-domains.txt',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        ) ?: [])
            ->map(fn (string $line) => Str::lower(trim($line)))
            ->reject(fn (string $line) => $line === '' || str_starts_with($line, '#'));

        return $bundled->merge(
            collect((array) $this->option('disposable_domains', []))
                ->map(fn ($domain) => Str::lower(trim((string) $domain)))
        );
    }
}
