# LazyWeb Application Endpoints

| Method + Path | Auth Required | Declaration (file:line) | Handler (file:line) | Middleware / Guards |
|---------------|---------------|-------------------------|---------------------|---------------------|
| GET / | Unauthenticated | page.php:4 | page.php:5-6 | AumWAF in init.php:34-38 |
| GET /index.php | Unauthenticated | index.php:1 | index.php:2 | AumWAF in init.php:34-38 |
| GET /core.php | Unauthenticated | core.php:1 | core.php:2 | AumWAF in init.php:34-38 |
| GET /page.php | Unauthenticated | page.php:1 | page.php:4-6 | AumWAF in init.php:34-38 |
| GET /save_session.php | Unauthenticated | save_session.php:1 | save_session.php:4-13 | AumWAF in init.php:34-38 |
| GET /build-customer-trust.php | Unauthenticated | build-customer-trust.php:1 | build-customer-trust.php:2 | AumWAF in init.php:34-38 |
| GET /cut-support-cost.php | Unauthenticated | cut-support-cost.php:1 | cut-support-cost.php:2 | AumWAF in init.php:34-38 |
| GET /s3cretadm1n/index.php | Authenticated (High Privilege) | s3cretadm1n/init.php:1 | s3cretadm1n/index.php:1-24 | AumWAF in init.php:34-38, isAdmin() check in s3cretadm1n/index.php:4-6 |
| GET /user/login.php | Unauthenticated | user/login.php:1 | user/login.php:10-41 | AumWAF in init.php:34-38 |
| GET /user/register.php | Unauthenticated | user/register.php:1 | user/register.php:10-35 | AumWAF in init.php:34-38 |
| POST /user/login.php | Unauthenticated | user/login.php:1 | user/login.php:10-41 | AumWAF in init.php:34-38 |
| POST /user/register.php | Unauthenticated | user/register.php:1 | user/register.php:10-35 | AumWAF in init.php:34-38 |
| GET/POST /user/profile.php | Authenticated (Low Privilege) | user/profile.php:1 | user/profile.php:10-31 | AumWAF in init.php:34-38, isAuth() check in user/profile.php:4-6 |