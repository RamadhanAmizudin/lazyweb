# Security Review Summary — LazyWeb

## Project Details
- **Project:** LazyWeb (LazyStatus) — hosted status page service
- **Language:** PHP 7.2 (custom procedural, no framework)
- **Database:** MySQL 5.6 via mysqli (no prepared statements)
- **Template Engine:** Smarty 3
- **Web Server:** Apache (php:7.2-apache Docker image)

## Review Scope
- **Total endpoints analyzed:** 17
- **Total sinks analyzed:** 20 (SQL, command execution, file inclusion, XML parsing, file upload)
- **Analysis methodology:** Sink→Source (reverse taint) + Source→Sink (forward taint) + adversarial validation

## Adversarial Validation Statistics
| Metric | Count |
|---|---|
| Candidates produced by sub agents | 14 |
| Killed by Reachability | 0 |
| Killed by Impact | 1 (register email check SELECT — result discarded, no data exfil) |
| Killed by Validity | 1 (LFI → RCE — `.php` suffix + `allow_url_include=Off` prevents RCE) |
| **Survived** | **12** |

## Findings by Category and Severity

| ID | Type | Endpoint | Auth Requirement | Confidence |
|---|---|---|---|---|
| VULN-001 | Auth Bypass | `GET /save_session.php?debug=1&admin=1` | Unauthenticated | High |
| VULN-002 | SQLi (Auth Bypass) | `POST /user/login.php` | Unauthenticated | High |
| VULN-003 | SQLi (INSERT) | `POST /user/register.php` | Unauthenticated | High |
| VULN-004 | SQLi (UPDATE) | `POST /user/profile.php` | Authenticated (Low) | High |
| VULN-005 | SQLi (SELECT) | `GET /user/index.php` | Authenticated (Low) | High |
| VULN-006 | SQLi (SELECT) | `GET/POST /user/inbox.php` | Authenticated (Low) | High |
| VULN-007 | SQLi (SELECT via helper) | `POST /user/inbox.php` | Authenticated (Low) | High |
| VULN-008 | Command Injection (RCE) | `GET/POST /user/add-site.php` | Authenticated (Low) | High |
| VULN-009 | SSRF | `GET/POST /user/add-site.php` | Authenticated (Low) | High |
| VULN-010 | RCE (system/eval) | `GET /s3cretadm1n/index.php` | Unauthenticated (via chain) | High |
| VULN-011 | XXE (File Read + SSRF) | `POST /user/api.php` | Unauthenticated | High |
| VULN-012 | IDOR (Delete) | `GET /user/add-site.php?delete=1&id=N` | Authenticated (Low) | High |

### By Category
- **SQL Injection:** 6 findings (VULN-002 through VULN-007)
- **Remote Code Execution:** 3 findings (VULN-001 via chain, VULN-008, VULN-010 via chain)
- **SSRF:** 1 finding (VULN-009)
- **XXE:** 1 finding (VULN-011)
- **IDOR:** 1 finding (VULN-012)

### By Authentication Requirement
- **Unauthenticated:** 4 findings (VULN-001, VULN-002, VULN-003, VULN-011)
- **Unauthenticated (via chain):** 1 finding (VULN-010)
- **Authenticated (Low Privilege):** 7 findings (VULN-004 through VULN-009, VULN-012)

## Critical Exploit Chains

### Chain 1: Unauthenticated → Full RCE
```
VULN-001 (Session Injection) → VULN-010 (Admin RCE)
GET /save_session.php?debug=1&admin=1 → GET /s3cretadm1n/index.php?c=id
```
**Result:** Unauthenticated attacker executes arbitrary OS commands as www-data.

### Chain 2: Stored XSS → Command Injection → RCE
```
VULN-008 (CMDI via stored URL) → shell_exec()
POST url='; cat /etc/passwd' → GET ?ping=1&id=1
```
**Result:** Authenticated attacker achieves RCE via stored shell metacharacters.

## Areas Analyzed With No Findings

| Area | Rationale |
|---|---|
| `save_session.php` (non-debug mode) | Debug mode disabled by default; only exploitable when `$_REQUEST['debug']` is set (which is itself a finding) |
| `user/profile.php` file upload | Filename forced to `{int user_id}.png` — prevents direct execution. LFI chain to RCE was killed by adversarial review (`.php` suffix blocks stream wrapper exploitation) |
| `user/inbox.php` view_id | `intval()` prevents SQL injection; IDOR on message viewing exists but is cosmetic (both if/else branches display the same data) — impact is below severity threshold |
| `user/add-site.php` delete (SQLi) | `intval()` on `get('id')` prevents SQL injection — safe |
| `user/add-site.php` ping (SQLi) | `intval()` on `get('id')` prevents SQL injection — safe |
| `init.php:21` getUsernameById() | Only called with DB values (from `inbox.php:37-38`), not direct user input — not exploitable as SQLi source |
| Third-party libraries (Smarty, jQuery, Bootstrap) | Out of scope — only unsafe usage patterns are reportable |

## Inconclusive Areas
None. All analyzed areas produced definitive results.

## WAF Assessment
AumWAF (`libs/AumWAF.class.php`) provides **negligible security**. It blocks only requests containing scanner tool names (`sqlmap`, `acunetix`, `nessus`, `bot`, `scan`, `zap`, `parros`, `injector`). Manual exploitation using curl, browser, or any non-signature tool passes through undetected. The WAF does not block SQL injection, command injection, XXE, SSRF, LFI, or any other vulnerability class found in this review.

## Key Systemic Issues
1. **No prepared statements** — all SQL queries use string concatenation or `sprintf('%s')`
2. **Cookie-based identity** — `cookie('user_id')` trusted as authoritative identity source across all endpoints
3. **No ownership checks** — no endpoint verifies the authenticated user owns the data being accessed/modified
4. **Debug mode user-controllable** — `$_REQUEST['debug']` toggles debug mode at runtime, enabling session injection
5. **shell_exec with user input** — URLs stored in DB are passed directly to shell commands
6. **No CSRF protection** — no tokens on any form
7. **XXE in XML parser** — all dangerous flags enabled, entity loading explicitly turned on