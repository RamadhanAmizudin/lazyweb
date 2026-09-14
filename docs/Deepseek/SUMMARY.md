# Security Review Summary — LazyStatus (`lazyweb`)

- **Endpoints:** [`ENDPOINTS.md`](./ENDPOINTS.md)
- **Target:** PHP 7.2 / Apache / MySQL 5.6 / Smarty 3 (`Dockerfile:1`)
- **Review type:** static, server-side; PoCs designed from code (not executed)

## Coverage
- **Endpoints analyzed:** 17 HTTP entry points (plus static assets and the `backup/` path).
- **Sinks analyzed:** 24 dangerous sinks — SQL construction (`init.php:21,26`,
  `login.php:15`, `register.php:19,23`, `index.php:16`, `profile.php:25,27,33`,
  `add-site.php:9,14,21,24`, `inbox.php:10,19,25,32`), OS command execution
  (`add-site.php:16`, `s3cretadm1n/index.php:19`), code evaluation
  (`s3cretadm1n/index.php:22`), dynamic file include (`page.php:6`), XML entity parsing
  (`api.php:5`), session assignment (`save_session.php:11`), file upload/write
  (`profile.php:13-19`), and static serving of `backup/db.sql` (`Dockerfile:7`).
- **Framework protections:** none effective. All SQL is raw concatenation via `mysqli`
  (no prepared statements/escaping). `AumWAF` (`libs/AumWAF.class.php:34-45`) is a
  case-insensitive substring blocklist (`sqlmap, acunetix, nessus, bot, scan, zap, parros,
  injector`) that performs no sanitization; it is absent entirely on `save_session.php` and
  `user/api.php`.

## Adversarial validation statistics
| Stage | Count |
|-------|-------|
| Raw candidate reports from Phase-2 reviewers | 16 |
| Unique candidates after dedup (Phase 3) | 11 |
| Killed by Reachability (Attack 1) | 0 |
| Killed by Impact (Attack 2) | 0 |
| Killed by Validity (Attack 3) | 0 |
| **Survived → reported** | **11** |

Deduplication merged three independent reports of the `add-site.php` command-injection sink,
three of the `user/api.php` XXE, and two of the `save_session.php`→admin RCE chain into single
findings. Every surviving finding was independently re-attacked by a
`security_reviewer_adversary` that did not author it.

## Findings by category
| Category | Count |
|----------|-------|
| RCE / OS command injection | 2 |
| Authentication bypass | 1 |
| SQL injection | 3 |
| Authorization bypass / IDOR | 2 |
| Sensitive data disclosure (XXE, LFI, backup dump) | 3 |

**Confidence:** High = 10, Medium = 1. (No Low tier exists; anything below Medium was
discarded.)

## Reported findings
| ID | Type | Endpoint | Auth Requirement | Confidence |
|----|------|----------|------------------|------------|
| [VULN-001](./VULN-001-auth-bypass-save-session.md) | auth-bypass | `GET/POST /save_session.php` | Unauthenticated | High |
| [VULN-002](./VULN-002-rce-admin-console.md) | rce | `GET /s3cretadm1n/index.php?c=\|p=` | Unauthenticated (via VULN-001) | High |
| [VULN-003](./VULN-003-sqli-login.md) | sqli | `POST /user/login.php` | Unauthenticated | High |
| [VULN-004](./VULN-004-sqli-register.md) | sqli | `POST /user/register.php` | Unauthenticated | High |
| [VULN-005](./VULN-005-idor-user-id-cookie.md) | idor | `GET/POST /user/profile.php`, `/user/index.php`, `/user/add-site.php` | Authenticated (Low) | High |
| [VULN-006](./VULN-006-idor-inbox-view-id.md) | idor | `GET /user/inbox.php?view_id=` | Authenticated (Low) | High |
| [VULN-007](./VULN-007-sqli-inbox-username.md) | sqli | `POST /user/inbox.php` (`to`) | Authenticated (Low) | High |
| [VULN-008](./VULN-008-data-disclosure-xxe-api.md) | data-disclosure (XXE/SSRF) | `POST /user/api.php` | Unauthenticated | High |
| [VULN-009](./VULN-009-rce-cmdi-ping.md) | rce (cmdi) | `GET/POST /user/add-site.php` | Authenticated (Low) | High |
| [VULN-010](./VULN-010-path-traversal-page-lfi.md) | path-traversal (LFI) | `GET /page.php?page=` | Unauthenticated | High |
| [VULN-011](./VULN-011-data-disclosure-db-backup.md) | data-disclosure | `GET /backup/db.sql` | Unauthenticated | Medium |

### Highest-impact chain
`VULN-001` (unauthenticated arbitrary `$_SESSION` write) → forged `admin` session →
`VULN-002` (`system()`/`eval()` console) = **unauthenticated RCE**.

## Areas analyzed with no findings
- **Avatar upload (`user/profile.php:11-20`).** The target path is
  `__DIR__ . '/avatar/' . (int)cookie('user_id') . '.png'`; the integer cast neutralizes
  `../` and extension injection, and `php:7.2-apache` only executes `.php`, so the fixed
  `.png` is never interpreted. No RCE/traversal.
- **`getUsernameById()` (`init.php:21`).** Reached only with integer DB column values
  (`inbox.php:37-41`), never a user-controlled string.
- **`intval`-cast parameters.** `add-site.php:9,14` and `inbox.php:32` cast the id/index to
  int before use; not injectable.
- **Smarty templates / SSTI.** Template names are static literals; no user-controlled
  template source or `{include file=$var}` path exists, so no SSTI or Smarty LFI.
  `templates_c/` is `0777` (`Dockerfile:11`) but writing compiled templates requires host/local
  access — excluded as out of scope.
- **Serialization/reflection.** No `unserialize()`, `ReflectionClass`, dynamic `new $class`,
  `call_user_func`, `yaml`, or `pickle` in first-party code.
- **Other injection classes.** No NoSQL, LDAP, XPath, or archive-extraction sinks; no
  `mail()`/webhook sender with user-controlled recipients.
- **`page.php` RCE chain.** Verified **not** RCE: `allow_url_include=Off` blocks `data://`
  and `php://input`, and the only file-write sink fixes `.png`. Reported as file/source
  disclosure only (`VULN-010`).
- **`phpinfo.php`.** Informational only — excluded.
- **`user/logout.php`, cookie flags.** Session-destruction and client-side concerns —
  excluded.

## Inconclusive areas
None. Every candidate reaching Phase 4 received a definitive `SURVIVED` verdict with cited
evidence.
