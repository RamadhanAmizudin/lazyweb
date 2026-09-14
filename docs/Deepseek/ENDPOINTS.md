# Attack Surface — LazyStatus (`lazyweb`)

PHP 7.2 / Apache / MySQL 5.6 / Smarty 3. No framework router: every `.php` file is
directly reachable by its path. The vendored `libs/smarty/*` tree is third-party and
out of scope.

**Global guards:**
- `init.php:34-38` runs `AumWAF` on every request that includes `init.php`. AumWAF is a
  weak case-insensitive substring blocklist (`libs/AumWAF.class.php:34-45`):
  `sqlmap`, `acunetix`, `nessus`, `bot`, `scan`, `zap`, `parros`, `injector`.
  It performs no SQL/command/path sanitization.
- `isAuth()` = `(bool) session('auth')` (`libs/common.php:12`).
- `isAdmin()` = `(bool) session('admin')` (`libs/common.php:16`).
- `get()/post()/cookie()/request()` return raw values with no sanitization
  (`libs/common.php:24-50`).

| Method + Path | Auth Required | Declaration (file:line) | Handler (file:line) | Middleware / Guards |
|---------------|---------------|-------------------------|---------------------|---------------------|
| GET `/index.php` (also `/`) | Unauthenticated | `index.php:1` | `index.php:4` | AumWAF (`init.php:34`) |
| GET `/core.php` | Unauthenticated | `core.php:1` | `core.php:4` | AumWAF |
| GET `/page.php` | Unauthenticated | `page.php:1` | `page.php:4-6` | AumWAF |
| GET `/build-customer-trust.php` | Unauthenticated | `build-customer-trust.php:1` | `:4` | AumWAF |
| GET `/cut-support-cost.php` | Unauthenticated | `cut-support-cost.php:1` | `:4` | AumWAF |
| GET/POST `/save_session.php` | Unauthenticated | `save_session.php:1` | `save_session.php:4-13` | none (only `config.php`, no `init.php`) |
| GET `/phpinfo.php` | Unauthenticated | `phpinfo.php:1` | `phpinfo.php:1` | none |
| POST `/user/login.php` | Unauthenticated | `user/login.php:1` | `user/login.php:10-41` | AumWAF; isAuth redirect |
| POST `/user/register.php` | Unauthenticated | `user/register.php:1` | `:10-34` | AumWAF; isAuth redirect |
| GET `/user/logout.php` | Unauthenticated | `user/logout.php:1` | `:4-6` | AumWAF |
| GET/POST `/user/index.php` | Authenticated (Low Privilege) | `user/index.php:1` | `:8-33` | isAuth (`:4`), AumWAF |
| GET/POST `/user/profile.php` | Authenticated (Low Privilege) | `user/profile.php:1` | `:10-34` | isAuth (`:4`), AumWAF |
| GET/POST `/user/add-site.php` | Authenticated (Low Privilege) | `user/add-site.php:1` | `:8-41` | isAuth (`:4`), AumWAF |
| GET/POST `/user/inbox.php` | Authenticated (Low Privilege) | `user/inbox.php:1` | `:8-51` | isAuth (`:4`), AumWAF |
| GET `/user/api-docs.php` | Authenticated (Low Privilege) | `user/api-docs.php:1` | `:8-12` | isAuth (`:4`), AumWAF |
| POST `/user/api.php` | Unauthenticated | `user/api.php:2` | `user/api.php:2-9` | none (does not include `init.php`) |
| GET `/s3cretadm1n/index.php` | Authenticated (High Privilege) | `s3cretadm1n/index.php:2` | `:17-23` | isAdmin (`:4`), AumWAF |
| GET `/backup/db.sql` | Unauthenticated (static) | — | — | none |

Static assets under `/user/css`, `/user/js`, `/user/images`, `/user/fonts`,
`/templates_c` are served directly and are not executable endpoints.
