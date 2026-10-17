-- Student Attendance Monitoring System — database schema
-- install.php runs this file for you. To set up by hand instead, import it in phpMyAdmin,
-- then open install.php once to create the first administrator account.

CREATE DATABASE IF NOT EXISTS attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance_db;

-- Staff accounts: administrators and teachers/instructors
CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(120) NULL,
    role           ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    failed_logins  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until   DATETIME     NULL,
    last_login     DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students; student_no is the Student ID printed on school IDs
CREATE TABLE IF NOT EXISTS students (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_no  VARCHAR(30)  NOT NULL UNIQUE,
    first_name  VARCHAR(60)  NOT NULL,
    last_name   VARCHAR(60)  NOT NULL,
    section     VARCHAR(50)  NULL,
    email       VARCHAR(120) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_students_name (last_name, first_name),
    KEY idx_students_section (section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classes / subjects, each with an assigned teacher or instructor
CREATE TABLE IF NOT EXISTS subjects (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    name        VARCHAR(120) NOT NULL,
    schedule    VARCHAR(100) NULL,
    teacher_id  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which students belong to which class
CREATE TABLE IF NOT EXISTS enrollments (
    student_id   INT UNSIGNED NOT NULL,
    subject_id   INT UNSIGNED NOT NULL,
    enrolled_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, subject_id),
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One attendance mark per student, per class, per day
CREATE TABLE IF NOT EXISTS attendance (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id       INT UNSIGNED NOT NULL,
    subject_id       INT UNSIGNED NOT NULL,
    teacher_id       INT UNSIGNED NULL,
    attendance_date  DATE         NOT NULL,
    attendance_time  TIME         NOT NULL,
    status           ENUM('Present', 'Late', 'Absent') NOT NULL,
    remarks          VARCHAR(255) NULL,
    recorded_by      INT UNSIGNED NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_mark (student_id, subject_id, attendance_date),
    KEY idx_attendance_date (attendance_date),
    KEY idx_attendance_subject_date (subject_id, attendance_date),
    KEY idx_attendance_status (status),
    CONSTRAINT fk_attendance_student  FOREIGN KEY (student_id)  REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_subject  FOREIGN KEY (subject_id)  REFERENCES subjects (id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_teacher  FOREIGN KEY (teacher_id)  REFERENCES users (id)    ON DELETE SET NULL,
    CONSTRAINT fk_attendance_recorder FOREIGN KEY (recorded_by) REFERENCES users (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
