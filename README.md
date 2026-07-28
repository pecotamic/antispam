# Pecotamic Antispam

![Statamic 5](https://img.shields.io/badge/Statamic-5.0+-26BBDD?style=for-the-badge&link=https://statamic.com)
![Statamic 6](https://img.shields.io/badge/Statamic-6.0+-FF269E?style=for-the-badge&link=https://statamic.com)

Silent server-side spam protection for Statamic forms.

The addon places an encrypted timing cookie only on pages containing a Statamic form action under /!/forms/. Submissions without a valid cookie, sent too quickly, or matching configured content patterns are silently discarded through Statamic's FormSubmitted event. Bots receive the regular success response.

## Installation

``` bash
composer require pecotamic/antispam
php artisan vendor:publish --tag=pecotamic-antispam-config
```

Configure protected form handles, timing limits, and optional regular expressions in config/pecotamic/antispam.php. Use * as the form handle to protect every Statamic form.
