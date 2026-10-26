# SCHOOP — refined & secured

Student/teacher portal for SHS San Nicolas III. PHP 8.1+ and MariaDB/MySQL (tested on PHP 8.3 / MariaDB 10.11; written to run on XAMPP's PHP 8.2 / MariaDB 10.4).

## Install (XAMPP)

1. Copy this folder to `htdocs/schoop`.
2. In phpMyAdmin (or the `mysql` CLI) create a database and import, **in this order**:
   `database/00_schoop_base.sql` (your newest dump, Mar 14, unchanged) then `database/01_security_upgrade.sql`.
3. Create a least-privilege DB user (don't run the app as `root`):
   ```sql
   CREATE USER 'schoop_app'@'localhost' IDENTIFIED BY 'a-long-random-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON schoop.* TO 'schoop_app'@'localhost';
   ```
4. Copy `app/config.sample.php` to `app/config.local.php` and put those credentials in. (This file is git-ignored.)
5. Give everyone who has no password a temporary one (prints/writes a CSV — hand them out privately, then delete it):
   ```
   php tools/issue_temp_passwords.php --missing --out=credentials.csv
   ```
   To (re)issue one account, including recovering a locked-out admin: `php tools/issue_temp_passwords.php --id 8364721`
6. Open `http://localhost/schoop/` and sign in.

**Production:** point the web server's DocumentRoot at `public/` and serve over HTTPS (uncomment the redirect in `public/.htaccess`). Then nothing outside `public/` is reachable at all.

## How accounts work now

- **Only an admin can create students and teachers** (Manage menu). The system generates a random temporary password, shows it once, and stores only its hash.
- On first sign-in the user must choose their own password before anything else opens. Temporary passwords expire after 7 days (`temp_password_days`).
- Forgot a password? An admin clicks **Reset password** — that also signs the person out everywhere.
- Students sign in with their 12-digit LRN, staff with their email. Login errors are identical for wrong password / unknown account / no password, and repeated failures are throttled.

## What each role can do

| | Student | Teacher | Admin |
|---|---|---|---|
| See own grades / announcements / calendar | ✔ | | |
| View & edit grades | | only subjects+sections assigned to them (`teacher_subjects`) | same (own teaching load) |
| Add/reset students & teachers, manage announcements | | | ✔ |

## Grades

Averages, weights, the minimum grade of 70 and the semester/quarter check are calculated **by your database triggers** (`calculate_grades`, `before_update_grades`, `enforce_min_quarter_grade`). The app deliberately doesn't recalculate anything; after saving it shows what the database stored. "Print / Save as PDF" uses the browser's print dialog with your header/footer letterhead.

## Layout

```
public/    the only web-facing folder (pages, api/, assets/)
app/       private PHP: config, db, auth, csrf, layout   (HTTP-denied)
database/  SQL                                          (HTTP-denied)
tools/     CLI scripts                                  (HTTP-denied, CLI-only)
```
Audit trail: the `audit_log` table records sign-ins, account creation, password resets and every grade change (old → new).

**Teacher/Admin**
- ven.squimay@gmail.com
- 1
**Teacher**
- buriklein@gmail.com
- kleinmabaho
**Admin**
- skibidisigmarizz@gmail.com
- skibidi
**Students**
- ICT Programming
  - 401726150119
  - thisis4test~
- HE
  - 593720468251
  - tinsay
- HUMMS
  - 276481593047
  - klein
- ICT CSS
  - 845329710584
  - burgos

**To all that made this research possible, thank you very very much.**
- **Student Researchers**
  - Adrian Oliver V. Hernandez (Research Leader)
  - Venedict Floyd S. Quimay (Assistant Leader)
  - Mark Zyrus A. Burgos
  - Kleinwise M. Florenciano
- **Research Adviser**
  - Mr. Roderick F. Ferros

