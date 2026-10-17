/* AttendTrack — small progressive enhancements. Every page still works without JavaScript. */
(function () {
    'use strict';

    // Mobile sidebar
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-toggle-sidebar]')) {
            document.body.classList.toggle('sidebar-open');
        } else if (event.target.closest('[data-close-sidebar]')) {
            document.body.classList.remove('sidebar-open');
        }
    });

    // Dark / light mode switch; the choice is remembered in this browser
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var root = document.documentElement;
            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('attendtrack-theme', next); } catch (e) { /* private mode: not remembered */ }
        });
    });

    // Confirm destructive actions: <form data-confirm="Delete this?">
    document.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Print buttons
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });

    // Dismissible alerts; success messages fade on their own
    document.querySelectorAll('.alert').forEach(function (alert) {
        var close = alert.querySelector('[data-dismiss]');
        if (close) {
            close.addEventListener('click', function () { alert.remove(); });
        }
        if (alert.classList.contains('alert-success') && close) {
            setTimeout(function () { alert.remove(); }, 7000);
        }
    });

    // ----- Take attendance roster -----
    var roster = document.querySelector('[data-roster]');
    if (roster) {
        var dirty = false;
        var rows = Array.prototype.slice.call(roster.querySelectorAll('tbody tr'));

        var updateTally = function () {
            var counts = { Present: 0, Late: 0, Absent: 0, unmarked: 0 };
            rows.forEach(function (row) {
                var picked = row.querySelector('input[type=radio]:checked');
                counts[picked ? picked.value : 'unmarked']++;
                row.classList.toggle('is-unmarked', !picked);
            });
            Object.keys(counts).forEach(function (key) {
                roster.querySelectorAll('[data-tally="' + key + '"]').forEach(function (el) {
                    el.textContent = counts[key];
                });
            });
        };

        roster.addEventListener('change', function () { dirty = true; updateTally(); });
        roster.addEventListener('input', function () { dirty = true; });

        roster.querySelectorAll('[data-mark]').forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.getAttribute('data-mark');
                rows.forEach(function (row) {
                    var radios = row.querySelectorAll('input[type=radio]');
                    var picked = row.querySelector('input[type=radio]:checked');
                    if (mode === 'clear') {
                        radios.forEach(function (r) { r.checked = false; });
                    } else if (mode === 'unmarked-present') {
                        if (!picked) { row.querySelector('input[value="Present"]').checked = true; }
                    } else {
                        row.querySelector('input[value="' + mode + '"]').checked = true;
                    }
                });
                dirty = true;
                updateTally();
            });
        });

        roster.addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (event) {
            if (dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
        updateTally();
    }

    // ----- Quick entry: fill the student name from the Student ID -----
    var studentInput = document.getElementById('student_no');
    var studentData = document.getElementById('students-data');
    if (studentInput && studentData) {
        var students = JSON.parse(studentData.textContent || '{}');
        var nameField = document.getElementById('student_name');
        var hint = document.getElementById('student_hint');
        var fill = function () {
            var id = studentInput.value.trim();
            var match = students[id];
            nameField.value = match || '';
            if (hint) {
                hint.textContent = id && !match ? 'No student with this ID yet.' : '';
            }
        };
        studentInput.addEventListener('input', fill);
        fill();
    }

    // ----- Enrollment: filter the student list and bulk-select what is shown -----
    var enroll = document.querySelector('[data-enroll]');
    if (enroll) {
        var textFilter = enroll.querySelector('[data-filter-text]');
        var sectionFilter = enroll.querySelector('[data-filter-section]');
        var enrollRows = Array.prototype.slice.call(enroll.querySelectorAll('tbody tr'));
        var counter = enroll.querySelector('[data-selected-count]');

        var countSelected = function () {
            counter.textContent = enroll.querySelectorAll('tbody input[type=checkbox]:checked').length;
        };
        var applyFilter = function () {
            var text = textFilter.value.trim().toLowerCase();
            var section = sectionFilter.value;
            enrollRows.forEach(function (row) {
                var visible = row.getAttribute('data-search').indexOf(text) !== -1
                    && (!section || row.getAttribute('data-section') === section);
                row.hidden = !visible;
            });
        };
        textFilter.addEventListener('input', applyFilter);
        sectionFilter.addEventListener('change', applyFilter);
        enroll.addEventListener('change', countSelected);
        enroll.querySelectorAll('[data-select-visible]').forEach(function (button) {
            button.addEventListener('click', function () {
                var on = button.getAttribute('data-select-visible') === '1';
                enrollRows.forEach(function (row) {
                    if (!row.hidden) { row.querySelector('input[type=checkbox]').checked = on; }
                });
                countSelected();
            });
        });
        countSelected();
    }
})();
