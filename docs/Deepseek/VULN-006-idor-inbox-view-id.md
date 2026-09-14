# Vulnerability: IDOR — `user/inbox.php` `view_id` has no ownership check

## Summary
`user/inbox.php` loads a support message by `id` from `intval(get('view_id'))` with no check
that the message belongs to the caller (`:31-47`). The intended ownership test is a no-op:
the `if($dmsg->to_id == cookie('user_id'))` branch (`:36-38`) and its `else` branch
(`:39-41`) contain byte-for-byte identical code, and the message fields are assigned
unconditionally at `:43-46`. Any authenticated user can therefore read any other user's
private support message by iterating the sequential `view_id`.

## Confidence
High — found by both taint directions and survived all three adversarial attacks with a
fully cited path.

## Authentication Requirement
Authenticated (Low Privilege) — any self-registered user (open registration).

## Attack Surface Location
- **Endpoint:** `GET /user/inbox.php?view_id=<n>`
- **Handler:** `user/inbox.php:31-47`
- **Framework protections bypassed:** AumWAF (`libs/AumWAF.class.php:34-45`) has no signature
  for a numeric `view_id`; `isAuth()` (`:4`) is satisfied by the attacker's own account.

## Attack Chain
1. **Initial access:** log in as any user.
2. **Endpoint interaction:** request `/user/inbox.php?view_id=1`, `2`, `3`, …
   (message ids are sequential — `tbl_support.id` AUTO_INCREMENT, `backup/db.sql:70-81`).
3. **Malicious input:** any integer `view_id` not addressed to the caller.
4. **Sink execution:** `SELECT * FROM tbl_support WHERE id = <id>` (`:32`) runs with no
   ownership predicate; the dead `if/else` (`:36-42`) applies no distinction and
   `$msg['message']`/`['subject']` are assigned unconditionally (`:43-45`).
5. **Post-exploitation:** private support content (potentially credentials/PII) is rendered
   by the template (`templates/user/inbox.php:69-77`).
6. **Final impact:** cross-user sensitive data disclosure.

## Data Flow
- **Source (user input):** HTTP query `view_id` (`get('view_id')`, `user/inbox.php:31`).
- **Sink:** `SELECT ... WHERE id = intval(get('view_id'))` then unconditional
  `$msg[...] = $dmsg->...` (`user/inbox.php:32-45`).

```
GET view_id → intval(get('view_id')) → SELECT tbl_support WHERE id=<id> [user/inbox.php:32]
  → $msg['message']=$dmsg->message [user/inbox.php:43] → templates/user/inbox.php:76
```

## Proof of Concept

### Prerequisites
- A valid low-privilege session (`PHPSESSID`).

### Exploit Request
```bash
T='http://{{TARGET_HOST}}'
# {{AUTH_TOKEN}} = PHPSESSID value for any registered account
curl -s -b "PHPSESSID={{AUTH_TOKEN}}" "$T/user/inbox.php?view_id=1"
curl -s -b "PHPSESSID={{AUTH_TOKEN}}" "$T/user/inbox.php?view_id=2"
```

### Expected Vulnerable Response
The rendered page shows `From`/`To`/`Message` for message id 1 (and 2, …) even though
`to_id` ≠ the attacker's id.

### Expected Patched Response
`SELECT ... WHERE id = <id> AND (to_id = <session id> OR user_id = <session id>)`;
otherwise no `$msg` is assigned and no message content is rendered.

### Impact Demonstration
By iterating `view_id`, the attacker dumps every user's private support messages.

## Affected Code
```php
// user/inbox.php:31-46
if(get('view_id')) {
	$q = $mysqli->query("SELECT * FROM tbl_support WHERE id = " . intval(get('view_id')));
	if($q->num_rows > 0) {
		$dmsg = $q->fetch_object();
		$msg = [];
		if($dmsg->to_id == cookie('user_id')) {
			$msg['from'] = getUsernameById($dmsg->user_id);
			$msg['to'] = getUsernameById($dmsg->to_id);
		} else {
			$msg['from'] = getUsernameById($dmsg->user_id);
			$msg['to'] = getUsernameById($dmsg->to_id);
		}
		$msg['message'] = $dmsg->message;
		$msg['subject'] = $dmsg->subject;
		$msg['created_at'] = $dmsg->created_at;
```

## Adversarial Review
Independent reviewer: `security_reviewer_adversary` (did not author this finding).

- **Reachability attack — FAILED to kill.** `view_id` is read raw (`user/inbox.php:31`); the
  only gate is `isAuth()` (`:4`), satisfiable via open registration; no WAF signature matches
  a plain integer.
- **Impact attack — FAILED to kill.** Arbitrary `tbl_support` rows (from/to/subject/message)
  are disclosed to an unrelated user (`:43-46`) — sensitive-data disclosure/IDOR, in scope;
  not self-harm.
- **Validity attack — FAILED to kill.** No ownership filter on the query (`:32`); the
  ownership test at `:36` is a no-op because both branches are identical (`:37-38` vs
  `:39-41`); `intval()` prevents SQLi but not access control; the template renders
  `{$msg.message}` whenever `$msg` is set (`templates/user/inbox.php:69,76`).

**Verdict: SURVIVED** (all three attacks failed).

## Remediation
Enforce ownership in SQL using the server-side session identity
(`AND (to_id = <session id> OR user_id = <session id>)`), and make the `else` branch deny
access rather than duplicate the allowed branch. Do not rely on the client `user_id` cookie
for authorization.
