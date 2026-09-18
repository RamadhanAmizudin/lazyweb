# Vulnerability: SQL Injection — User Dashboard via user_id Parameter

## Summary
The `user/index.php` endpoint passes `get('user_id')` or `cookie('user_id')` directly into a SQL query via string concatenation at line 16. An authenticated attacker can inject arbitrary SQL to extract data from the database, including other users' site records and any table data via UNION injection.

## Confidence
High

## Authentication Requirement
Authenticated (Low Privilege)

## Attack Surface Location
- **Endpoint:** `GET /user/index.php?user_id=...`
- **Handler:** `user/index.php:1-34`
- **Middleware / Guards:** AumWAF, `isAuth()` check

## Attack Chain
1. **Initial access:** Attacker authenticates as any registered user.
2. **Endpoint interaction:** The index handler reads `get('user_id')` at line 9 (or falls back to `cookie('user_id')` at line 11) and passes it to a SQL query.
3. **Malicious input:** `user_id` GET param = `1 UNION SELECT 1,2,3,4,5,6 FROM tbl_users` to exfiltrate user data.
4. **Sink execution:** `$mysqli->query('SELECT * FROM tbl_sites WHERE user_id = ' . $user_id)` at line 16.
5. **Post-exploitation:** Attacker extracts all database data via UNION injection. The query results are rendered in the template.
6. **Final impact:** Full data disclosure of all database tables.

## Data Flow
- **Source (user input):** `$_GET['user_id']` or `$_COOKIE['user_id']`
- **Sink:** `$mysqli->query()` at `user/index.php:16`

```
$_GET['user_id'] → get('user_id') [common.php:28-29] → $user_id [index.php:9] → 'SELECT * FROM tbl_sites WHERE user_id = ' . $user_id [index.php:16] → $mysqli->query() [index.php:16]
```

## Proof of Concept

### Prerequisites
- Valid session cookie from any registered user account.

### Exploit Request — Extract User Credentials
```bash
curl "http://{{TARGET_HOST}}/user/index.php?user_id=1+UNION+SELECT+1,username,userpass,useremail,5+FROM+tbl_users" \
  -b "PHPSESSID={{SESSION_ID}}"
```

### Expected Vulnerable Response
The page renders site records including the injected rows containing usernames, password hashes, and emails from `tbl_users`.

### Expected Patched Response
After parameterized queries, the injection is treated as literal data — the query returns only the authenticated user's own sites.

### Impact Demonstration
Attacker can extract all user credentials, password hashes, and any other database data via UNION-based SQL injection.

## Affected Code
```php
// user/index.php:8-16
if( !empty( get('user_id') ) ) {
    $user_id = get('user_id');
} elseif( !empty( cookie('user_id') ) ) {
    $user_id = cookie('user_id');
}

$query = $mysqli->query('SELECT * FROM tbl_sites WHERE user_id = ' . $user_id);
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `GET /user/index.php` requires only `isAuth()` — any registered user can reach it. `get('user_id')` reads directly from `$_GET`.
- **Impact attack — FAILED to kill.** SQL injection on a `SELECT *` query enables full data exfiltration via UNION injection.
- **Validity attack — FAILED to kill.** Direct string concatenation with no escaping. No prepared statements.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Remove the `get('user_id')` path. Always derive `user_id` from the session:
```php
$user_id = intval($_SESSION['id']);
$query = $mysqli->query('SELECT * FROM tbl_sites WHERE user_id = ' . $user_id);
```