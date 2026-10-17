<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = APP_NAME . ' · ' . SCHOOL_NAME;
require __DIR__ . '/includes/site_header.php';
?>
<section class="hero">
    <div class="site-wrap">
        <div>
            <span class="eyebrow"><?= icon('check') ?> <?= e(APP_NAME) ?></span>
            <h1>Every class. Every student. <em>Accounted for.</em></h1>
            <p class="lead">Mark students Present, Late or Absent in seconds. The date and time are saved automatically, and teachers can monitor attendance with daily records, status lists and percentage reports.</p>
            <div class="btn-row">
                <a class="btn btn-primary" href="<?= e(page('admin/login.php')) ?>"><?= icon('lock') ?> Staff login</a>
                <a class="btn btn-light" href="#check"><?= icon('search') ?> Check my attendance</a>
            </div>
            <div class="hero-stats">
                <div><strong>3</strong><span>Statuses: Present, Late, Absent</span></div>
                <div><strong>6</strong><span>Ready-made reports</span></div>
                <div><strong>CSV</strong><span>Export &amp; print</span></div>
            </div>
        </div>

        <div class="lookup-card" id="check">
            <h2>Check your attendance</h2>
            <p>Students can view their attendance summary and percentage for each class.</p>
            <form class="form-stack" method="get" action="<?= e(page('check.php')) ?>">
                <div class="field">
                    <label for="sid">Student ID</label>
                    <input id="sid" name="student_no" placeholder="e.g. 2026-0001" required maxlength="30" autocomplete="off">
                </div>
                <div class="field">
                    <label for="lname">Last name</label>
                    <input id="lname" name="last_name" placeholder="As registered with the school" required maxlength="60">
                </div>
                <button class="btn btn-primary btn-block" type="submit"><?= icon('search') ?> View my attendance</button>
            </form>
            <p class="lookup-note"><?= icon('lock') ?> Your last name is used to confirm it's you. Only attendance information is shown.</p>
        </div>
    </div>
</section>

<section class="section" id="features">
    <div class="site-wrap">
        <div class="section-head">
            <h2>Everything a class needs to track attendance</h2>
            <p>Built for teachers and instructors, with an admin panel to manage students, classes and staff.</p>
        </div>
        <div class="features">
            <div class="feature present">
                <div class="feature-icon"><?= icon('user-check') ?></div>
                <h3>Record attendance fast</h3>
                <p>Open a class list, tap Present, Late or Absent for each student and save. Or use Quick Entry to mark one student by Student ID.</p>
            </div>
            <div class="feature info">
                <div class="feature-icon"><?= icon('clock') ?></div>
                <h3>Date &amp; time saved automatically</h3>
                <p>Each mark stores the date, time, class, and the teacher or instructor. Saving again updates the student's record for that day.</p>
            </div>
            <div class="feature">
                <div class="feature-icon"><?= icon('dashboard') ?></div>
                <h3>Monitor in real time</h3>
                <p>The dashboard shows today's Present, Late and Absent counts, the last 7 days, and students who fall below <?= (int) LOW_ATTENDANCE_THRESHOLD ?>%.</p>
            </div>
            <div class="feature late">
                <div class="feature-icon"><?= icon('calendar') ?></div>
                <h3>Daily attendance record</h3>
                <p>See every class for any day, including students who haven't been marked yet.</p>
            </div>
            <div class="feature absent">
                <div class="feature-icon"><?= icon('list') ?></div>
                <h3>Present, Absent &amp; Late lists</h3>
                <p>Filter by date range, class and section, or group by student to see who is absent or late most often.</p>
            </div>
            <div class="feature">
                <div class="feature-icon"><?= icon('pie') ?></div>
                <h3>Reports &amp; percentages</h3>
                <p>Per-student reports and an attendance summary with percentages, ready to print or export to Excel (CSV).</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-alt" id="how">
    <div class="site-wrap">
        <div class="section-head">
            <h2>How it works</h2>
            <p>Three steps from class list to report.</p>
        </div>
        <div class="steps">
            <div class="step">
                <h3>Set up classes</h3>
                <p>The administrator adds students, creates classes or subjects, assigns an instructor and enrolls students.</p>
            </div>
            <div class="step">
                <h3>Take attendance</h3>
                <p>Teachers sign in, pick their class and date, and mark each student. The time is stamped automatically.</p>
            </div>
            <div class="step">
                <h3>Monitor &amp; report</h3>
                <p>Review records, print daily lists, and track each student's attendance percentage over time.</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="site-wrap">
        <div class="cta">
            <div>
                <h2>Teachers and administrators</h2>
                <p>Sign in to take attendance and view reports.</p>
            </div>
            <a class="btn btn-light" href="<?= e(page('admin/login.php')) ?>"><?= icon('lock') ?> Go to staff login</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
