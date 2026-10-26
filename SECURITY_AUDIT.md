# What was wrong, what changed

## Critical problems in the original repo (all fixed)

| # | Problem | Where |
|---|---|---|
| 1 | **Anyone could reset any account's password, admin included**, with one unauthenticated request | `hashpass/hash_password.php` |
| 2 | **Anyone could write grades for any student** — no login check at all | `backend/bulk_update.php`, `dashupdate.php` |
| 3 | **Anyone could claim an account that had no password** by knowing its LRN/email. Right now that includes admin `buriklein@gmail.com` | `login.php` / `set-password*` |
| 4 | Anyone could add students (no login check) | `add-students.php` |
| 5 | Pages were protected only by JavaScript redirects (`index.html`, `add_teacher.html`); the HTML and data endpoints were open | `check.js`, static `.html` |
| 6 | PHP errors shown to visitors (`display_errors` on); DB connects as `root` with no password | `db.php` |
| 7 | Login said "User not found" vs "Invalid password" (account enumeration); no throttling; session id not regenerated; a dead `logon.php` compared plaintext passwords | `login.php`, `logon.php` |
| 8 | No CSRF protection anywhere; announcements deleted via a plain link (GET); stored-XSS patterns (`innerHTML`, inline `onclick` with data) | `edit_announcements.php`, `dashfetch.js`, `add_teacher.html` |
| 9 | Student/teacher data fetched from public endpoints (`announcements.php`) | `backend/` |
| 10 | The "set password" flows could not work at all (mixed mysqli/PDO, missing includes, needed a session that only exists after login) | `setpassword.php`, `set-password.php` |

## SQL comparison
- `schoop.sql` (Mar 14) is **byte-identical** to the one inside `schoop.sql.zip` — the zip adds nothing.
- `schoop (1).sql` (Mar 9, also inside `schoop.zip`) and `information.sql` (Feb 14) are older.
- Mar 14 vs Mar 9: adds `announcements`, two teachers (47 vs 45), nullable passwords; grade score columns changed `decimal` → `int` (no data lost: every old score was whole); drops `teacher_advisory` (not referenced by any code). **Used Mar 14 as the base.**
- `information.sql` (oldest) contains a plaintext password and a real-looking Gmail address.

## Database fixes (`01_security_upgrade.sql`)
- Added temp-password columns, `login_attempts`, `audit_log`; normalised `''` passwords to `NULL`.
- **Dropped trigger `after_student_insert`.** It made *adding any student fail* ("Invalid quarter for this subject semester"): it tried to insert all four quarters for every subject, which the grade trigger rejects. Grade rows are created on first entry instead.

## Still on you (I can't do these from here)
1. **Make the GitHub repo private and remove the SQL dumps/zips from its history** (`git filter-repo`). A "private portal" shouldn't have its data public, even placeholder data. If the `adhernandez587@gmail.com` account/password in `information.sql` is real or reused anywhere, change it.
2. Serve over **HTTPS** in production; use the DB user from the README; keep backups.
3. Four teachers (2029803, 3286317, 3527366, 7166651) have no email, so they cannot sign in until you add one.
4. Two grade rows in the seed data (about 91.62→91 and 84.53→83) don't match the trigger formula; they were probably edited by hand.
5. There is still no screen for assigning subjects to teachers (`teacher_subjects` is edited in the database). Teachers/admins only see sections they teach.
6. I replaced the html2canvas/jsPDF export with browser print → "Save as PDF" (the old one loaded scripts from a CDN and hard-coded the subject name "Computer Programming").
