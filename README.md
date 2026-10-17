# Student Attendance Monitoring System (AttendTrack)

A PHP + MySQL website for XAMPP with a public site, a student attendance lookup, and an admin panel for administrators and teachers. Dark mode is on by default, with a sun/moon switch on every page.

> **New to this? Follow the [step-by-step Setup Guide](SETUP-GUIDE.md).** It assumes no technical knowledge.

## Quick setup (XAMPP)

1. Put the project folder at `C:\xampp\htdocs\attendance\` (with `index.php` directly inside it).
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Go to **http://localhost/attendance/install**.
4. Create your administrator account (choose your own username and password). Leave **Add sample data** ticked to get 3 classes, 15 students and three weeks of attendance to explore.
5. Sign in at **http://localhost/attendance/admin/login**.

The installer creates the `attendance_db` database and tables for you. Once an administrator exists, the installer hides itself: visiting it redirects to the home page, and it can't create more administrators.

To set up by hand instead, import `sql/schema.sql` in phpMyAdmin, then open `/install` once to create the admin account.

### Clean web addresses

The root `.htaccess` uses Apache's `mod_rewrite` (on by default in XAMPP), so addresses have no `.php`: `/attendance/` instead of `/attendance/index.php`, and `/attendance/admin/students` instead of `/attendance/admin/students.php`. Old `.php` links redirect to the clean versions. If `mod_rewrite` is unavailable, the site detects this and keeps using `.php` links. Web access to `.git/` is blocked here, and to `includes/` and `sql/` by their own `.htaccess` files.

## Configuration

Edit `config.php`:

| Setting | Default | Notes |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` | `127.0.0.1`, `3306`, `root`, *(empty)* | XAMPP defaults |
| `DB_NAME` | `attendance_db` | |
| `SCHOOL_NAME` | `Your School Name` | Shown on the website and printed reports |
| `DEFAULT_THEME` | `dark` | `dark` or `light` for first-time visitors. Each browser remembers its own choice |
| `APP_TIMEZONE` | `Asia/Shanghai` | Used for attendance date/time stamps |
| `LATE_COUNTS_AS_PRESENT` | `true` | Whether Late counts toward the attendance % |
| `LOW_ATTENDANCE_THRESHOLD` | `75` | Students below this % are flagged |

## What's included

**Public website**
- `index.php`: landing page.
- `check.php`: students enter their Student ID and last name to see their attendance per class, overall %, and recent records.

**Admin panel** (`/admin`)

| Area | Page | What it does |
|---|---|---|
| Overview | Dashboard | Today's Present/Late/Absent counts and rate, 7-day chart, today's classes, students below the threshold, recent activity |
| Attendance | Take Attendance | Pick a class and date, then mark every student Present / Late / Absent (with remarks). Saving again updates the records |
| | Quick Entry | Mark one student: Student ID (name fills in), class/subject, status, teacher/instructor, date, time |
| | Attendance Records | Filter by date range, class, status, section or student; edit or delete records; export CSV |
| Reports | Daily Attendance | All classes for a day, including students not yet marked |
| | Present / Absent / Late Students | Lists for a date range, per record or grouped per student |
| | Student Report | One student's totals, per-class percentages and full history |
| | Summary & Percentage | Per-class and per-student totals and attendance %, sortable |
| Management (admin only) | Students | Add, edit and delete students, and pick their classes |
| | Classes & Subjects | Classes with schedules and instructors; enroll students |
| | Staff Accounts | Administrator and teacher logins; deactivate accounts |

Every report can be printed (with a print-only header) or exported to CSV for Excel.

**Roles**
- **Administrator**: everything, including students, classes and staff accounts.
- **Teacher**: takes attendance and views records and reports for the classes assigned to them only.

## How attendance is stored

Each mark saves the student, class/subject, teacher/instructor, **date**, **time**, status (`Present`, `Late`, `Absent`), optional remarks, and who recorded it. A student has one mark per class per day, so saving again **updates** that record.

Attendance % = (Present + Late) ÷ sessions recorded × 100 (or Present only if `LATE_COUNTS_AS_PRESENT` is `false`).

## Security notes

- Passwords are hashed with `password_hash()`. Accounts lock for 5 minutes after 5 failed sign-ins.
- All queries use prepared statements, all output is HTML-escaped, and every form has a CSRF token.
- `includes/` and `sql/` are blocked from direct web access by `.htaccess`.
- This is set up for local XAMPP use. Before putting it on a public server, set a MySQL password, serve it over HTTPS, and delete `install.php`.

## Files

```
attendance/
├── index.php, check.php        Public website
├── install.php                 One-time installer
├── config.php                  Settings
├── .htaccess                   Clean addresses (no .php)
├── SETUP-GUIDE.md              Step-by-step guide for non-technical users
├── admin/                      Admin panel pages
├── includes/                   Shared PHP (auth, database, layout, helpers)
├── assets/css, js, img         Styles, scripts, favicon
└── sql/schema.sql              Database schema
```
