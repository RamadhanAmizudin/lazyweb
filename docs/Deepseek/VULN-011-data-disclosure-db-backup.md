# Vulnerability: Sensitive Data Disclosure — Database dump served from the web root

## Summary
`Dockerfile:7` (`COPY . /var/www/html/`) copies the entire build context, including
`backup/db.sql`, into the Apache document root. `backup/db.sql` is served statically and
unauthenticated; it contains the `tbl_admins` row (admin username + bcrypt password hash) and
the complete database schema. `robots.txt` only disallows `/user` and `/s3cretadm1n`, and no
`.htaccess`/Apache deny rule or `.dockerignore` exists.

## Confidence
Medium — identified primarily by the sink→source (reverse) direction (the "sink" is Apache's
static file handler) and survived all three adversarial attacks with a cited path.

## Authentication Requirement
Unauthenticated.

## Attack Surface Location
- **Endpoint:** `GET /backup/db.sql`
- **Handler:** static file copied into the document root by `Dockerfile:7`
- **Framework protections bypassed:** none — Apache serves `.sql` as static content; there is
  no auth guard, deny rule, or `.dockerignore`.

## Attack Chain
1. **Initial access:** unauthenticated request to `/backup/db.sql`.
2. **Endpoint interaction:** Apache returns the file as-is.
3. **Malicious input:** none required.
4. **Sink execution:** static file read.
5. **Post-exploitation:** recover `adminuser='poweranger'` / bcrypt `adminpass` and the full
   schema; crack the hash offline; reuse credentials.
6. **Final impact:** disclosure of administrative credential material and database structure.

## Data Flow
- **Source:** file on disk created at deploy time (`Dockerfile:7`, context `backup/db.sql`).
- **Sink:** static HTTP serving of `/backup/db.sql` by Apache (document root `/var/www/html`).

```
backup/db.sql → COPY . /var/www/html/ [Dockerfile:7] → /var/www/html/backup/db.sql
  → GET /backup/db.sql (static, no auth)
```

## Proof of Concept

### Prerequisites
- None.

### Exploit Request
```bash
curl -s 'http://{{TARGET_HOST}}/backup/db.sql'
```

### Expected Vulnerable Response
```sql
INSERT INTO `tbl_admins` (`id`, `adminuser`, `adminpass`) VALUES
(1, 'poweranger', '$2y$10$MjOkG21zLKsUOWvEuU.TFak3eWMoAvYHX2Tz53MbYz4VxVsa1fFa244');
```

### Expected Patched Response
`404 Not Found` / `403 Forbidden` (file moved outside the document root or denied by server
configuration).

### Impact Demonstration
A full MySQL dump with an admin username and password hash is disclosed without
authentication; the hash can be cracked offline and the schema reveals all tables.

## Affected Code
```dockerfile
# Dockerfile:7
COPY . /var/www/html/
```
```sql
-- backup/db.sql:39-40
INSERT INTO `tbl_admins` (`id`, `adminuser`, `adminpass`) VALUES
(1, 'poweranger', '$2y$10$MjOkG21zLKsUOWvEuU.TFak3eWMoAvYHX2Tz53MbYz4VxVsa1fFa244');
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `Dockerfile:7` copies the context into
  `/var/www/html/`; `backup/db.sql` exists in the context; Apache serves `*.sql` statically
  with no `.htaccess`/deny rule; `robots.txt:1-3` disallows only `/user` and `/s3cretadm1n`.
- **Impact attack — FAILED to kill.** The response contains admin credential material
  (bcrypt hash, `backup/db.sql:40`) and full schema — unauthenticated sensitive-data
  disclosure, in scope. The deployment/config origin does not remove reachability or impact.
- **Validity attack — FAILED to kill.** The data is genuinely in the served file; no
  sanitizer, access control, or framework protection applies to static serving.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Keep database dumps out of the document root (add a `.dockerignore` excluding `backup/`, or
copy only the application files), deny `/backup/` in the Apache configuration, and rotate the
exposed admin credential.
