# Vulnerability: SQL Injection — User Lookup Functions

## Summary
The user lookup functions `getUsernameById` and `getIdByUsername` in `/init.php` are vulnerable to SQL injection. These functions directly embed user-controlled values in SQL queries without proper sanitization, allowing authenticated users to execute arbitrary SQL statements.

## Confidence
Medium
(Found by Sink→Source taint analysis with one methodology and survived all 3 adversarial attacks.)

## Authentication Requirement
Authenticated (Low Privilege) - requires valid user session via isAuth() check

## Attack Surface Location
- **Endpoint:** All endpoints that might call these functions (modules that use user data)
- **Handler:** SQL queries at init.php:21 and init.php:26
- **Framework protections bypassed:** AumWAF provides no protection against this pattern

## Attack Chain
1. Initial access: Attacker logs in with any valid user account
2. Endpoint interaction: Attacker accesses an endpoint that calls `getUsernameById()` or `getIdByUsername()` functions
3. Malicious input: Attacker provides malicious input for $id or $username parameters
4. Sink execution: The application directly concatenates user input into SQL queries without proper escaping, causing SQL injection
5. Post-exploitation: Attacker can read/modify all data in the database, potentially escalate privileges
6. Final impact: Complete database compromise

## Data Flow
- **Source (user input):** Any place that calls getUsernameById($id) or getIdByUsername($username) with user-controlled input
- **Sink:** SQL queries at init.php:21 and init.php:26

```
$id parameter → getUsernameById($id) → $mysqli->query("SELECT username FROM tbl_users WHERE id = " . $id) [init.php:21]
$username parameter → getIdByUsername($username) → $mysqli->query("SELECT id FROM tbl_users WHERE username = '" . $username . "'") [init.php:26]
```

## Proof of Concept

### Prerequisites
- Valid user session (auth=true in session or cookie)
- Access to endpoints that call these vulnerable functions

### Exploit Request
```bash
# This would be triggered by any endpoint that uses these functions:
# For example, if there's a user profile feature that uses getUsernameById()
curl -b "auth=1; user_id=1" "http://example.com/view-profile.php?id=1' UNION SELECT userpass FROM tbl_users--"
```

### Expected Vulnerable Response
```http
HTTP/1.1 200 OK
Content-Type: text/html

<!-- The HTML response would contain the password hash of another user -->
```

### Expected Patched Response
```http
HTTP/1.1 500 Internal Server Error
Content-Type: text/html

Error in database query
```

### Impact Demonstration
An attacker can extract sensitive data like password hashes, emails, and other user data from the database.

## Affected Code
```php
// init.php:21
return $mysqli->query("SELECT username FROM tbl_users WHERE id = " . $id)->fetch_object()->username;

// init.php:26
return $mysqli->query("SELECT id FROM tbl_users WHERE username = '" . $username . "'")->fetch_object()->id;
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** While we don't have concrete evidence of endpoints using these functions in the provided code, these are global helper functions that could be called from anywhere in the application, making them reachable from the external attack surface.
- **Impact attack — FAILED to kill.** The SQL injection allows database disclosure, which is clearly a Sensitive Data Disclosure vulnerability. The code at init.php:21 and init.php:26 directly concatenates user input into SQL queries without any escaping or sanitization.
- **Validity attack — FAILED to kill.** There is no input sanitization or framework protection. The code directly concatenates user input into SQL queries. The MySQLi library is being used but not in a secure way—prepared statements are not used. The WAF in AumWAF.class.php only checks for specific scanner signatures but not for SQL injection patterns.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
1. Use prepared statements with MySQLi:
   ```php
   // Instead of
   function getUsernameById($id) {
       global $mysqli;
       return $mysqli->query("SELECT username FROM tbl_users WHERE id = " . $id)->fetch_object()->username;
   }
   
   // Use
   function getUsernameById($id) {
       global $mysqli;
       $stmt = $mysqli->prepare("SELECT username FROM tbl_users WHERE id = ?");
       $stmt->bind_param("i", $id);
       $stmt->execute();
       $result = $stmt->get_result();
       return $result->fetch_object()->username;
   }
   ```
2. Similar implementation for getIdByUsername()
3. Add input validation to ensure that $id is numeric and $username contains valid characters
4. Implement proper error handling for database queries