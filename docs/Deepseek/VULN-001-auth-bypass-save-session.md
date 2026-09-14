# Vulnerability: Authentication Bypass — Unauthenticated arbitrary `$_SESSION` write via request-controlled `debug`

## Summary
`config.php:14` derives `$_CONFIG['debug']` directly from the client-supplied `debug`
request parameter. `save_session.php` requires only `config.php` (it does **not** include
`init.php`, so no `AumWAF` and no auth guard run) and, whenever `debug` is truthy, copies
**every** `$_REQUEST` key/value straight into `$_SESSION` (`save_session.php:10-12`). Any
unauthenticated client can therefore create a session and set `$_SESSION['auth']` and
`$_SESSION['admin']`. Because `isAuth()`/`isAdmin()` trust only those session booleans
(`libs/common.php:12-18`) and no code path ever binds them to a server-side identity, the
attacker obtains a fully authenticated — and administrator — session. This directly chains
into the command-execution console at `/s3cretadm1n/index.php` (see `VULN-002`).

## Confidence
High — found by both taint directions (Step 2 sink→source and Step 3 source→sink) and
survived all three adversarial attacks with a fully cited path.

## Authentication Requirement
Unauthenticated.

## Attack Surface Location
- **Endpoint:** `GET/POST /save_session.php` (then `GET /s3cretadm1n/index.php`)
- **Handler:** `save_session.php:8-13`; gate `config.php:14`; consumer `s3cretadm1n/index.php:4`
- **Framework protections bypassed:** `save_session.php` includes only `config.php`
  (`save_session.php:2`), so `AumWAF` (`init.php:34-38`) is never invoked. The WAF, when it
  does run, only blocks the substrings `sqlmap/acunetix/nessus/bot/scan/zap/parros/injector`
  (`libs/AumWAF.class.php:34-45`) — `debug`, `admin`, `auth` are not in the list.

## Attack Chain
1. **Initial access:** attacker sends an unauthenticated request to `/save_session.php`.
2. **Endpoint interaction:** a `PHPSESSID` is issued (`session_start()` at
   `save_session.php:4-6`; `strlen(session_id())` is `0` before start, so the guard always
   starts a session).
3. **Malicious input:** `?debug=1&admin=1&auth=1`. `debug` flips `$_CONFIG['debug']`
   (`config.php:14`), enabling the write loop.
4. **Sink execution:** `foreach ($_REQUEST as $key => $val) { $_SESSION[$key] = $val; }`
   (`save_session.php:10-12`) stores `auth=1` and `admin=1`; PHP persists the session at
   request shutdown.
5. **Post-exploitation:** reusing the same `PHPSESSID`, the attacker satisfies `isAuth()`
   and `isAdmin()` (`libs/common.php:12-18`), reaching the admin console
   (`s3cretadm1n/index.php:4`) and its `system()`/`eval()` sinks (`VULN-002`).
6. **Final impact:** complete authentication/authorization bypass → remote code execution
   as the web-server user.

## Data Flow
- **Source (user input):** `$_REQUEST['debug']` (gate) and arbitrary `$_REQUEST` keys
  `auth`/`admin` (payload).
- **Sink:** `$_SESSION[$key] = $val` at `save_session.php:11`, consumed by
  `session('admin')` at `libs/common.php:16`.

```
$_REQUEST['debug'] [config.php:14] → $_CONFIG['debug'] → if(debug) [save_session.php:8]
$_REQUEST['admin'] [save_session.php:10] → $_SESSION['admin'] [save_session.php:11]
  → session('admin') [libs/common.php:16] → isAdmin() [s3cretadm1n/index.php:4]
```

## Proof of Concept

### Prerequisites
- Network reachability to the application. No credentials required.

### Exploit Request
```bash
# 1) Obtain a server-issued session cookie
curl -s -c /tmp/jar.txt 'http://{{TARGET_HOST}}/save_session.php' -o /dev/null
# 2) Poison the session: debug enables the write loop; inject auth + admin
curl -s -b /tmp/jar.txt -c /tmp/jar.txt \
  'http://{{TARGET_HOST}}/save_session.php?debug=1&admin=1&auth=1'
# 3) Confirm the forged admin session reaches the admin console
curl -s -b /tmp/jar.txt 'http://{{TARGET_HOST}}/s3cretadm1n/index.php?c=id'
```

### Expected Vulnerable Response
Step 2 returns a `print_r($_SESSION)` dump containing `[admin] => 1` and `[auth] => 1`.
Step 3 returns the secret admin page plus the output of `id`
(`uid=33(www-data) gid=33(www-data) ...`).

### Expected Patched Response
`save_session.php` no longer copies arbitrary request keys into the session, and admin
state can only be set by a server-side admin login. Step 3 then returns a `302` redirect to
`/` (`s3cretadm1n/index.php:4-6`) and no command output.

### Impact Demonstration
An unauthenticated attacker forges an administrator session, taking full control of the
application and executing OS commands on the server (see `VULN-002`).

## Affected Code
```php
// config.php:14
'debug' => (!empty($_REQUEST['debug'])) ? true : false
```
```php
// save_session.php:1-13
<?php
require __DIR__ . '/config.php';
if (strlen(session_id()) < 1) { session_start(); }
if( $_CONFIG['debug'] ) {
	header('Content-type: text/plain');
	foreach($_REQUEST as $key => $val) {
		$_SESSION[$key] = $val;
	}
	print_r($_SESSION);
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `save_session.php:2` requires only `config.php`,
  so no `init.php`/AumWAF (`init.php:34-38`) and no auth guard run. `$_CONFIG['debug']` is
  request-controlled (`config.php:14`); the write loop is live code (`save_session.php:10-12`);
  the endpoint is a directly reachable `.php` file. No internal-network/host access needed.
- **Impact attack — FAILED to kill.** The injected `admin`/`auth` booleans flip the sole
  guards (`libs/common.php:16`, `s3cretadm1n/index.php:4`) and `(bool)"1"` is true. The
  result is a real authentication/authorization bypass, in scope.
- **Validity attack — FAILED to kill.** No sanitizer, allow-list, or type constraint exists;
  `$_REQUEST` is raw (`libs/common.php:36-38,48-50`); WAF signatures (`libs/AumWAF.class.php:34-45`)
  do not match `debug`/`admin`/`auth`; no other code binds the session flags to a credential.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Remove the request-controlled `debug` toggle from `config.php`; never copy `$_REQUEST` into
`$_SESSION`; delete or protect `save_session.php` (require `init.php` + authentication);
enforce authentication/authorization from server-side state bound to the authenticated
principal, and call `session_regenerate_id(true)` on any privilege change.
