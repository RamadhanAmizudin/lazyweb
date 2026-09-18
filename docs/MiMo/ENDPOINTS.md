# LazyWeb — Endpoint Map

| Method + Path | Auth Required | Declaration (file:line) | Handler (file:line) | Middleware / Guards |
|---|---|---|---|---|
| GET / | Unauthenticated | index.php:1 | index.php:1-4 | AumWAF |
| GET /page.php?page={name} | Unauthenticated | page.php:1 | page.php:1-9 | AumWAF |
| GET /phpinfo.php | Unauthenticated | phpinfo.php:1 | phpinfo.php:1 | AumWAF |
| GET /save_session.php?debug=1 | Unauthenticated | save_session.php:1 | save_session.php:1-14 | AumWAF |
| GET/POST /user/login.php | Unauthenticated | user/login.php:1 | user/login.php:1-44 | AumWAF |
| GET/POST /user/register.php | Unauthenticated | user/register.php:1 | user/register.php:1-38 | AumWAF |
| POST /user/api.php | Unauthenticated | user/api.php:1 | user/api.php:1-10 | AumWAF |
| GET /user/index.php | Authenticated (Low Privilege) | user/index.php:1 | user/index.php:1-34 | AumWAF, isAuth() |
| GET/POST /user/add-site.php | Authenticated (Low Privilege) | user/add-site.php:1 | user/add-site.php:1-42 | AumWAF, isAuth() |
| GET/POST /user/profile.php | Authenticated (Low Privilege) | user/profile.php:1 | user/profile.php:1-36 | AumWAF, isAuth() |
| GET/POST /user/inbox.php | Authenticated (Low Privilege) | user/inbox.php:1 | user/inbox.php:1-52 | AumWAF, isAuth() |
| GET /s3cretadm1n/index.php | Authenticated (High Privilege) | s3cretadm1n/index.php:1 | s3cretadm1n/index.php:1-24 | AumWAF, isAdmin() |