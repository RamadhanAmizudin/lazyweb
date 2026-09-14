# Vulnerability: SQL Injection — Authenticated `to` parameter via `getIdByUsername()`

## Summary
`user/inbox.php` passes `post('to')` into `getIdByUsername()` (`init.php:26`), which
concatenates it directly into a SELECT:
`"SELECT id FROM tbl_users WHERE username = '" . $username . "'"`. The surrounding
`sprintf(... '%d' ...)` in `inbox.php:10` casts only the function's *return value* — the
injected SELECT has already executed by then. An authenticated low-privilege user can inject
SQL through the "To" field, enabling blind/UNION extraction of arbitrary database contents.
`init.php:21` (`getUsernameById`) is also concatenated but is reached only with integer DB
values, so it is not user-controllable.

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path.

## Authentication Requirement
Authenticated (Low Privilege) — any self-registered user.

## Attack Surface Location
- **Endpoint:** `POST /user/inbox.php`
- **Handler:** `user/inbox.php:10` → `init.php:26`
- **Framework protections bypassed:** AumWAF (`libs/AumWAF.class.php:34-45`) blocks only the
  literal substrings `sqlmap/acunetix/nessus/bot/scan/zap/parros/injector`; a normal payload
  such as `' UNION SELECT 1#` contains none. No escaping/parameterization.

## Attack Chain
1. **Initial access:** self-register and log in, obtaining `PHPSESSID` (and `user_id` cookie).
2. **Endpoint interaction:** POST a message with non-empty `to`, `subject`, `message`.
3. **Malicious input:** `to=x' UNION SELECT SLEEP(5)-- -` (or a UNION/boolean payload).
4. **Sink execution:** `getIdByUsername(post('to'))` runs the concatenated SELECT
   (`init.php:26`) before its result is formatted as `%d` (`inbox.php:10`).
5. **Post-exploitation:** time/boolean oracle or UNION-based extraction reads
   `tbl_users`/`tbl_admins`.
6. **Final impact:** sensitive database disclosure from an authenticated endpoint.

## Data Flow
- **Source (user input):** POST `to` (`user/inbox.php:10`).
- **Sink:** `mysqli::query` inside `getIdByUsername()` (`init.php:26`).

```
post('to') → getIdByUsername($username) [init.php:25-26]
  → "SELECT id FROM tbl_users WHERE username = '" . $username . "'" [init.php:26]
  → $mysqli->query(...) [init.php:26]
  (result is then used as %d in sprintf at user/inbox.php:10 — after execution)
```

## Proof of Concept

### Prerequisites
- A valid low-privilege session.

### Exploit Request
```bash
T='http://{{TARGET_HOST}}'
curl -s -o /dev/null -w '%{time_total}\n' \
  -b "PHPSESSID={{AUTH_TOKEN}}" -X POST "$T/user/inbox.php" \
  --data-urlencode "to=x' UNION SELECT SLEEP(5)-- -" \
  --data-urlencode 'subject=s' \
  --data-urlencode 'message=m'
```

### Expected Vulnerable Response
Response delayed ~5 seconds (the injected `SLEEP(5)` executes), then a redirect to
`inbox.php`.

### Expected Patched Response
`post('to')` is bound as a string parameter; the injected payload is treated as a username
literal and the response returns immediately.

### Impact Demonstration
Time/boolean/UNION extraction of DB contents (e.g. `tbl_users.userpass`) through the
authenticated message-send flow.

## Affected Code
```php
// init.php:24-27
function getIdByUsername($username) {
	global $mysqli;
	return $mysqli->query("SELECT id FROM tbl_users WHERE username = '" . $username . "'")->fetch_object()->id;
}
```
```php
// user/inbox.php:9-11
if(!empty(post('to')) && !empty(post('subject')) && !empty(post('message'))) {
	$sql = sprintf("INSERT INTO tbl_support (user_id, to_id, subject, message) values ('%d', '%d', '%s', '%s')", cookie('user_id'), getIdByUsername(post('to')), post('subject'), post('message'));
	$mysqli->query($sql);
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `user/inbox.php:8-10` is reached after `isAuth()`
  (`:4`); registration is open; `post('to')` is raw (`libs/common.php:32,48-50`); the WAF
  substring list does not match a normal SQLi payload.
- **Impact attack — FAILED to kill.** SQL injection is explicitly in scope; it can read
  `tbl_users`/`tbl_admins` and mutate data.
- **Validity attack — FAILED to kill.** `init.php:26` concatenates the raw value; `%d` in
  `inbox.php:10` applies to the return value only, after the injected SELECT has run; no
  sanitizer/allow-list exists.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Use prepared statements for all lookups (`getIdByUsername`) and for the INSERT; validate
that the recipient exists via a parameterized query. Never concatenate request data into SQL.
