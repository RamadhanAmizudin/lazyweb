# Vulnerability: Authentication Bypass — Session Privilege Escalation

## Summary
The save_session.php endpoint allows arbitrary session parameter creation when debug mode is enabled. An attacker can set any session variables including 'admin=1' which grants administrative privileges, bypassing proper authentication mechanisms.

## Confidence
High
(Found by both Sink→Source and Source→Sink taint analysis with fully cited path and survived all 3 adversarial attacks.)

## Authentication Requirement
Regular user - requires a valid user session (created through normal registration and login) plus access to debug mode

## Attack Surface Location
- **Endpoint:** `GET /save_session.php?debug=true`
- **Handler:** Session variable setting at save_session.php:11
- **Framework protections bypassed:** AumWAF provides no protection against this pattern

## Attack Chain
1. Initial access: Attacker registers as a regular user and logs in normally
2. Endpoint interaction: Attacker accesses save_session.php with debug=true parameter
3. Malicious input: Attacker provides admin=1 and auth=1 as POST or GET parameters
4. Sink execution: The application directly sets these values in the session without validation
5. Post-exploitation: Attacker now has admin privileges and can access admin-only endpoints
6. Final impact: Complete administrative access to the application, including remote code execution

## Data Flow
- **Source (user input):** GET/POST parameters in $_REQUEST
- **Sink:** Session variable setting at save_session.php:11

```
GET/POST admin=1 → $_REQUEST['admin'] → $_SESSION['admin'] = 1 [save_session.php:11]
GET/POST auth=1 → $_REQUEST['auth'] → $_SESSION['auth'] = 1 [save_session.php:11]
```

## Proof of Concept

### Prerequisites
- Regular user session (created through normal registration and login)

### Exploit Request
```bash
curl "http://example.com/save_session.php?debug=true&admin=1&auth=1"
```

### Expected Vulnerable Response
```http
HTTP/1.1 200 OK
Content-Type: text/plain

Array
(
    [admin] => 1
    [auth] => 1
)
```

### Expected Patched Response
```http
HTTP/1.1 403 Forbidden
Content-Type: text/html

Invalid request
```

### Impact Demonstration
An attacker who was previously just a regular user can now:
1. Access admin-only endpoints like /s3cretadm1n/index.php
2. Execute arbitrary code on the server via those endpoints
3. Access/modify all application data
4. Bypass all authorization checks that rely on the admin session variable

## Affected Code
```php
// save_session.php:11
$_SESSION[$key] = $val;
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The endpoint is reachable from external network at `/save_session.php` after regular user login. The debug condition at save_session.php:8-11is satisfied because debug mode is enabled by setting debug=true in the request parameters.
- **Impact attack — FAILED to kill.** Setting admin=1 in the session at save_session.php:11 provides full administrative access, which is clearly an Authentication Bypass vulnerability. This leads to remote code execution when combined with the admin panel vulnerabilities.
- **Validity attack — FAILED to kill.** There is no validation of session variables before they are set at save_session.php:11. The code simply copies all request parameters to the session when debug mode is enabled. The WAF in AumWAF.class.php only checks for specific scanner signatures but not for parameter manipulation.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
1. Remove or heavily restrict the save_session.php endpoint, especially the debug functionality
2. Never set sensitive session variables based on user input
3. Use a separate, secure method for maintaining admin sessions
4. Add proper validation before setting any session variables
5. If debug functionality is needed, restrict it to specific IP addresses or require additional authentication
6. Consider implementing more robust session management with secure flags