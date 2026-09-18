# Vulnerability: SQL Injection — Profile UPDATE via Username, Email, and Cookie

## Summary
The `user/profile.php` endpoint passes `post('username')`, `post('useremail')`, and `cookie('user_id')` directly into UPDATE and SELECT queries via string concatenation at lines 25, 27, and 33. An authenticated attacker can inject arbitrary SQL to modify any user's data, extract information from the database, or escalate privileges.

## Confidence
High

## Authentication Requirement
Authenticated (Low Privilege)

## Attack Surface Location
- **Endpoint:** `POST /user/profile.php`
- **Handler:** `user/profile.php:1-36`
- **Middleware / Guards:** AumWAF, `isAuth()` check

## Attack Chain
1. **Initial access:** Attacker authenticates as any registered user.
2. **Endpoint interaction:** The profile handler reads `post('username')`, `post('useremail')`, and `cookie('user_id')`, passing them directly to SQL queries.
3. **Malicious input:** `username` = `admin' WHERE id=1 -- -` to modify the admin user's record. Or `user_id` cookie = `1 OR 1=1` to update all users.
4. **Sink execution:** `$mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "'... WHERE id = " . cookie('user_id'))` at lines 25/27.
5. **Post-exploitation:** Attacker modifies admin credentials, extracts data via the SELECT at line 33.
6. **Final impact:** Data manipulation, authentication bypass (credential overwrite), data disclosure.

## Data Flow
- **Source (user input):** `$_POST['username']`, `$_POST['useremail']`, `$_COOKIE['user_id']`
- **Sink:** `$mysqli->query()` at `user/profile.php:25`, `user/profile.php:27`, `user/profile.php:33`

```
$_POST['username'] → post('username') [common.php:32-33] → string concatenation [profile.php:25/27] → $mysqli->query() [profile.php:25/27]
$_COOKIE['user_id'] → cookie('user_id') [common.php:24-25] → WHERE id = " . cookie('user_id') [profile.php:25/27/33] → $mysqli->query() [profile.php:25/27/33]
```

## Proof of Concept

### Prerequisites
- Valid session cookie from any registered user account.

### Exploit Request — Overwrite Admin Credentials
```bash
curl -X POST "http://{{TARGET_HOST}}/user/profile.php" \
  -b "PHPSESSID={{SESSION_ID}}; user_id=1" \
  -d "username=admin&useremail=attacker@evil.com'+WHERE+id=1+--+"
```

### Expected Vulnerable Response
The UPDATE modifies the admin user's email. The SELECT with cookie injection returns admin credentials in the profile page template.

### Expected Patched Response
After parameterized queries, the injection is treated as literal data — the UPDATE only modifies the authenticated user's own record.

### Impact Demonstration
Attacker can overwrite any user's credentials (including admin), gaining full account takeover. Data exfiltration via the SELECT sink at line 33.

## Affected Code
```php
// user/profile.php:23-28
if( !empty( post('username') ) || !empty('useremail')) {
    if(!empty(post('password'))) {
        $mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "', userpass='" . pw(post('password')) . "' WHERE id = " . cookie('user_id'));
    } else {
        $mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "' WHERE id = " . cookie('user_id'));
    }
}

// user/profile.php:33
$q = $mysqli->query('SELECT * FROM tbl_users WHERE id = ' . cookie('user_id'));
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `POST /user/profile.php` requires only `isAuth()` — any registered user can reach it. All input parameters are user-controlled.
- **Impact attack — FAILED to kill.** SQL injection on UPDATE enables credential overwrite (auth bypass) and data manipulation. The SELECT enables data exfiltration.
- **Validity attack — FAILED to kill.** Direct string concatenation with no escaping. No prepared statements. `pw()` only hashes the password parameter.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Use prepared statements and derive user_id from session:
```php
$user_id = intval($_SESSION['id']);
$stmt = $mysqli->prepare("UPDATE tbl_users SET username = ?, useremail = ? WHERE id = ?");
$stmt->bind_param("ssi", post('username'), post('useremail'), $user_id);
$stmt->execute();
```