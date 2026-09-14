# Vulnerability: SQL Injection — Unauthenticated registration `usermail` / `username`

## Summary
`user/register.php` concatenates the POST `usermail` field into a raw SELECT (`:19`) and
passes `username`/`usermail` into a `sprintf`-built INSERT (`:23`) with no escaping. An
unauthenticated attacker can use the "Email exists in our database" vs. INSERT/redirect
behavior as a boolean oracle for blind extraction of arbitrary database contents (including
`tbl_users`/`tbl_admins`), and can perform INSERT injection to write attacker-controlled
rows.

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path.

## Authentication Requirement
Unauthenticated.

## Attack Surface Location
- **Endpoint:** `POST /user/register.php`
- **Handler:** `user/register.php:12-24`
- **Framework protections bypassed:** AumWAF (`libs/AumWAF.class.php:34-45`) blocks only the
  literal substrings `sqlmap/acunetix/nessus/bot/scan/zap/parros/injector`; a manual payload
  such as `' OR (SELECT ...) -- ` contains none. No escaping/parameterization.

## Attack Chain
1. **Initial access:** attacker reaches `/user/register.php` unauthenticated.
2. **Endpoint interaction:** submits registration with matching passwords (`p1 == p2`).
3. **Malicious input:** `usermail` (SELECT) and/or `username` (INSERT) containing SQL syntax,
   e.g. `' OR (SELECT SUBSTRING(userpass,1,1) FROM tbl_users LIMIT 1)='$'-- -`.
4. **Sink execution:** `$mysqli->query("SELECT * FROM tbl_users WHERE useremail = '" . $email . "'")`
   (`:19`) and `sprintf("INSERT INTO tbl_users (username, userpass, useremail) VALUES ('%s','%s','%s')", $username, pw($p2), $email)`
   (`:23-24`).
5. **Post-exploitation:** boolean/time oracle reveals DB contents; INSERT injection writes
   attacker-chosen rows.
6. **Final impact:** sensitive database disclosure and data manipulation.

## Data Flow
- **Source (user input):** POST `usermail`, `username` (`user/register.php:12-13`).
- **Sink:** `mysqli::query` (`user/register.php:19`, `:24`).

```
post('usermail') → $email [user/register.php:12]
  → "SELECT * FROM tbl_users WHERE useremail = '" . $email . "'" [user/register.php:19] → query [user/register.php:19]
post('username')/$email → sprintf INSERT ... [user/register.php:23] → query [user/register.php:24]
```

## Proof of Concept

### Prerequisites
None.

### Exploit Request
```bash
# Time-based confirmation of the injection in the SELECT
curl -s -o /dev/null -w '%{time_total}\n' -X POST 'http://{{TARGET_HOST}}/user/register.php' \
  --data-urlencode "usermail=' UNION SELECT SLEEP(5),1,2,3-- -" \
  --data-urlencode 'username=probe' \
  --data-urlencode 'p1=a' --data-urlencode 'p2=a'
```

### Expected Vulnerable Response
Response delayed ~5 seconds (the `SLEEP(5)` in the UNION executes), then
`Email exists in our database`.

### Expected Patched Response
Immediate response; `usermail` is treated as a literal string (prepared statement) and the
database is never queried with injected syntax.

### Impact Demonstration
Blind/UNION extraction of database contents from an unauthenticated endpoint, e.g. dumping
`tbl_users.userpass` hashes.

## Affected Code
```php
// user/register.php:19
$q = $mysqli->query("SELECT * FROM tbl_users WHERE useremail = '" . $email . "'");
// user/register.php:23
$sql = sprintf("INSERT INTO tbl_users (username, userpass, useremail) VALUES ('%s', '%s', '%s')", $username, pw($p2), $email);
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `user/register.php:2` → `user/init.php:2` →
  root `init.php`; the only guard is `isAuth()` (`:4-6`), false for anonymous users.
  `post('usermail')` is raw (`:12`) and reaches `:19`/`:23`.
- **Impact attack — FAILED to kill.** The `:20-21` vs. `:23-28` branches form a boolean
  oracle; on PHP 7.2 a failed query only raises a notice, so error payloads fall through and
  the oracle holds. Real disclosure/manipulation, in scope.
- **Validity attack — FAILED to kill.** Raw concatenation/`sprintf('%s')`, no sanitizer, no
  allow-list on `usermail`/`username`.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Use prepared statements with bound parameters for the SELECT and INSERT; validate the email
address and username format server-side before use.
