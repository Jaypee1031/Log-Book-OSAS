<?php
/**
 * Database Connection & Auto-Migration Handler
 * Guidance Office Logbook Management System (OSAS)
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$db_host = 'localhost';
$db_port = 3306;
$db_name = 'guidance_logbook_db';
$db_user = 'root';
$db_pass = '';

try {
    // Connect to server (create DB if not exists)
    $dsn_server = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
    $pdo_temp = new PDO($dsn_server, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo_temp = null;

    // Connect to actual database
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Ensure schema and initial seeds exist
    initialize_database_schema($pdo);

} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:24px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:30px auto;max-width:600px;'>
        <h2 style='margin-top:0;'>Database Connection Error</h2>
        <p>Could not connect to MySQL server at <code>{$db_host}:{$db_port}</code>.</p>
        <p><strong>Error details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please ensure XAMPP MySQL service is running on port {$db_port}.</p>
    </div>");
}

function initialize_database_schema(PDO $pdo) {
    // Check if users table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($table_check) {
        return; // Already initialized
    }

    // 1. Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NULL,
        `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_login_at` DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Logbook Types table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `logbook_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `slug` VARCHAR(64) NOT NULL UNIQUE,
        `title` VARCHAR(128) NOT NULL,
        `description` TEXT NULL,
        `icon` VARCHAR(64) NOT NULL DEFAULT 'bi-journal-text',
        `color_theme` VARCHAR(32) NOT NULL DEFAULT 'blue',
        `is_system` TINYINT(1) NOT NULL DEFAULT 0,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Logbook Fields table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `logbook_fields` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `logbook_type_id` INT NOT NULL,
        `field_key` VARCHAR(64) NOT NULL,
        `field_label` VARCHAR(128) NOT NULL,
        `field_type` VARCHAR(32) NOT NULL DEFAULT 'text',
        `options_json` TEXT NULL,
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `show_in_table` TINYINT(1) NOT NULL DEFAULT 1,
        `show_in_filter` TINYINT(1) NOT NULL DEFAULT 1,
        `display_order` INT NOT NULL DEFAULT 0,
        `placeholder` VARCHAR(128) NULL,
        `help_text` VARCHAR(255) NULL,
        INDEX (`logbook_type_id`),
        CONSTRAINT `fk_fields_type` FOREIGN KEY (`logbook_type_id`) REFERENCES `logbook_types`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Logbook Records table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `logbook_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `logbook_type_id` INT NOT NULL,
        `record_no` INT NOT NULL,
        `entry_date` DATE NOT NULL,
        `client_name` VARCHAR(255) NOT NULL,
        `client_type` VARCHAR(64) NULL,
        `purpose` VARCHAR(255) NULL,
        `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
        `notes` TEXT NULL,
        `data_json` LONGTEXT NULL,
        `created_by` INT NULL,
        `updated_by` INT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL,
        INDEX (`logbook_type_id`, `record_no`),
        INDEX (`entry_date`),
        INDEX (`status`),
        CONSTRAINT `fk_records_type` FOREIGN KEY (`logbook_type_id`) REFERENCES `logbook_types`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Audit Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `action` VARCHAR(64) NOT NULL,
        `logbook_type_id` INT NULL,
        `record_id` INT NULL,
        `details` TEXT NULL,
        `ip_address` VARCHAR(45) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`action`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. System Settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_key` VARCHAR(64) PRIMARY KEY,
        `setting_value` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed default settings
    $settings = [
        'institution_name' => 'Quirino State University (QSU) - Diffun Campus',
        'office_name' => 'Office of Student Affairs and Services (OSAS)',
        'office_unit' => 'Office of Guidance & Counseling Affairs (OGCA)',
        'office_address' => 'Diffun, Quirino 3401, Philippines',
        'system_title' => 'QSU Guidance Office Logbook System',
        'guidance_head' => 'Maria Elena Santos, RGC',
        'guidance_head_title' => 'Head, Guidance and Counseling Office',
        'osas_director' => 'Dr. Fernando Gomez, Ed.D.',
        'osas_director_title' => 'Director, Student Affairs and Services',
        'facebook_url' => 'https://www.facebook.com/profile.php?id=61595216319061'
    ];
    $stmt = $pdo->prepare("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // Seed default administrator (password: 'admin123')
    $admin_pass = password_hash('admin123', PASSWORD_BCRYPT);

    $user_stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role`, `is_active`) VALUES (?, ?, ?, ?, 'admin', 1)");
    $user_stmt->execute(['admin', $admin_pass, 'Dr. Carmela Reyes (Administrator)', 'admin@guidance.osas.edu']);
    $admin_id = $pdo->lastInsertId();

    // Seed 4 Core Logbook Types from Logbook.docx
    $types = [
        [
            'slug' => 'borrowers-log',
            'title' => "Borrower's Log",
            'description' => "Tracks materials, testing kits, equipment, and resources borrowed by students, faculty, alumni, or visitors.",
            'icon' => 'bi-box-arrow-right',
            'color_theme' => 'blue',
            'is_system' => 1,
            'display_order' => 1
        ],
        [
            'slug' => 'good-moral-log',
            'title' => 'Certificate of Good Moral Character',
            'description' => 'Official registry for tracking requests, processing, and issuance of Good Moral Certificates.',
            'icon' => 'bi-award',
            'color_theme' => 'emerald',
            'is_system' => 1,
            'display_order' => 2
        ],
        [
            'slug' => 'clients-log',
            'title' => "Client's Log",
            'description' => 'Records students, alumni, faculty, and visitors receiving counseling, consultation, clearance, or general office services.',
            'icon' => 'bi-people',
            'color_theme' => 'indigo',
            'is_system' => 1,
            'display_order' => 3
        ],
        [
            'slug' => 'visitors-log',
            'title' => "Visitor's Log",
            'description' => 'Maintains a formal log of official agency guests, company partners, parents, and campus visitors.',
            'icon' => 'bi-building-check',
            'color_theme' => 'amber',
            'is_system' => 1,
            'display_order' => 4
        ]
    ];

    $type_stmt = $pdo->prepare("INSERT INTO `logbook_types` (`slug`, `title`, `description`, `icon`, `color_theme`, `is_system`, `display_order`, `is_active`) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $field_stmt = $pdo->prepare("INSERT INTO `logbook_fields` (`logbook_type_id`, `field_key`, `field_label`, `field_type`, `options_json`, `is_required`, `show_in_table`, `show_in_filter`, `display_order`, `placeholder`, `help_text`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Standard QSU Diffun Programs with Year Levels
    $qsu_programs = [
        'Bachelor of Science in Criminology' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor in Elementary Education' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor of Secondary Education' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor of Science in Forestry' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor of Science in Tourism Management' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor of Science in Hospitality Management' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor of Science in Nutrition & Dietetics' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor of Science in Agriculture' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor of Technology and Livelihood Education' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Health Care Service & Caregiving NC II' => ['1st Year', '2nd Year'],
        'Bachelor of Science in Office Administration' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'Bachelor of Science in Agricultural and Biosystems Engineering' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
    ];
    $qsu_courses_list = [];
    foreach ($qsu_programs as $qp => $qyrs) {
        foreach ($qyrs as $qyr) {
            $qsu_courses_list[] = "$qp - $qyr";
        }
    }
    $qsu_courses_list[] = 'Faculty / Staff / Administrative Office';
    $qsu_courses_list[] = 'None';
    $qsu_courses_list[] = 'Other';
    $qsu_courses_json = json_encode($qsu_courses_list, JSON_UNESCAPED_UNICODE);

    // 1. Borrower's Log Fields
    $type_stmt->execute(array_values($types[0]));
    $borrower_id = $pdo->lastInsertId();

    $b_fields = [
        ['date', 'Date', 'date', null, 1, 1, 1, 1, '', 'Date item was borrowed'],
        ['name', 'Name', 'text', null, 1, 1, 1, 2, 'Full Name of Borrower', 'Last Name, First Name'],
        ['client_type', 'Client Type', 'select', json_encode(['Student', 'Visitor', 'Alumni', 'Faculty']), 1, 1, 1, 3, '', 'Select classification'],
        ['course_year_dept', 'Course and Year / Department', 'select', $qsu_courses_json, 1, 1, 1, 4, '— Select Course & Year / Department —', 'Academic degree or office department'],
        ['purpose', 'Purpose / Item Borrowed', 'text', null, 1, 1, 1, 5, 'e.g. Projector, psychological test manual, guidance kit', 'Specific item or reason'],
        ['date_of_return', 'Date of Return', 'date', null, 0, 1, 1, 6, '', 'Leave blank if not yet returned'],
        ['return_status', 'Return Status', 'select', json_encode(['Pending', 'Returned', 'Overdue']), 1, 1, 1, 7, '', 'Tracking status'],
        ['remarks', 'Remarks', 'textarea', null, 0, 1, 0, 8, 'Condition, serial #, or notes', 'Optional remarks']
    ];
    foreach ($b_fields as $f) {
        $field_stmt->execute(array_merge([$borrower_id], $f));
    }

    // 2. Certificate of Good Moral Character Fields
    $type_stmt->execute(array_values($types[1]));
    $moral_id = $pdo->lastInsertId();

    $m_fields = [
        ['date', 'Date Requested', 'date', null, 1, 1, 1, 1, '', 'Application date'],
        ['year_graduated', 'Year Graduated / Level', 'text', null, 1, 1, 1, 2, 'e.g. 2024 or 4th Year BSIT', 'Year graduated or current level'],
        ['name', 'Name', 'text', null, 1, 1, 1, 3, 'Full Name of Student / Graduate', 'Last Name, First Name M.I.'],
        ['purpose', 'Purpose', 'select', json_encode(['PRC/Government Exam', 'Scholarship', 'Transfer', 'Employment', 'Other']), 1, 1, 1, 4, '', 'Select primary purpose'],
        ['purpose_details', 'Purpose Details / Agency', 'text', null, 0, 0, 0, 5, 'Specify if Other or exam/company name', 'Detailed reason or agency name'],
        ['signature_confirmed', 'Signature / Confirmation', 'signature_confirm', null, 1, 1, 1, 6, '', 'Acknowledge request and identity verification'],
        ['date_issued', 'Date Issued', 'date', null, 0, 1, 1, 7, '', 'Date printed and officially released'],
        ['remarks', 'Remarks / Tracking Code', 'text', null, 0, 1, 0, 8, 'e.g. OR #104928, Doc Stamp Attached', 'Optional tracking or notes']
    ];
    foreach ($m_fields as $f) {
        $field_stmt->execute(array_merge([$moral_id], $f));
    }

    // 3. Client's Log Fields
    $type_stmt->execute(array_values($types[2]));
    $client_id = $pdo->lastInsertId();

    $c_fields = [
        ['date', 'Date', 'date', null, 1, 1, 1, 1, '', 'Visit date'],
        ['name', 'Name', 'text', null, 1, 1, 1, 2, 'Full Name', 'Last Name, First Name'],
        ['client_type', 'Client Type', 'select', json_encode(['Student', 'Visitor', 'Alumni', 'Faculty']), 1, 1, 1, 3, '', 'Select classification'],
        ['course_year', 'Course & Year / Office', 'select', $qsu_courses_json, 1, 1, 1, 4, '— Select Course & Year / Office —', 'Course and year or college'],
        ['purpose', 'Purpose of Visit', 'select', json_encode(['Routine Counseling', 'Walk-in Consultation', 'Clearance Signing', 'Certificate Request', 'Exit Interview', 'Inquiry / Information', 'Special Assistance', 'Other']), 1, 1, 1, 5, '', 'Reason for guidance office visit'],
        ['time_in', 'Time In', 'time', null, 1, 1, 0, 6, '', 'Arrival time'],
        ['time_out', 'Time Out', 'time', null, 0, 1, 0, 7, '', 'Departure time (can be recorded on exit)'],
        ['signature_confirmed', 'Signature / Confirmation', 'signature_confirm', null, 1, 1, 1, 8, '', 'Client confirmed attendance'],
        ['remarks', 'Remarks / Counselor Action', 'textarea', null, 0, 1, 0, 9, 'Confidential or administrative remarks', 'Optional office note']
    ];
    foreach ($c_fields as $f) {
        $field_stmt->execute(array_merge([$client_id], $f));
    }

    // 4. Visitor's Log Fields
    $type_stmt->execute(array_values($types[3]));
    $visitor_id = $pdo->lastInsertId();

    $v_fields = [
        ['date', 'Date', 'date', null, 1, 1, 1, 1, '', 'Date of visit'],
        ['name', 'Name', 'text', null, 1, 1, 1, 2, 'Full Name of Visitor', 'Guest or Representative Name'],
        ['agency', 'Agency / Institution / Office', 'select', json_encode([
            'Department of Education (DepEd)',
            'Commission on Higher Education (CHED)',
            'Department of Social Welfare and Development (DSWD)',
            'Department of Health (DOH) / RHU',
            'Local Government Unit (LGU)',
            'Philippine National Police (PNP)',
            'Technical Education and Skills Development Authority (TESDA)',
            'Quirino State University (QSU Office / Department)',
            'Private Company / Industry Partner',
            'Parent / Guardian',
            'None',
            'Other'
        ]), 1, 1, 1, 3, '— Select Agency / Institution / Office —', 'Name of represented office or company'],
        ['purpose', 'Purpose of Visit', 'select', json_encode([
            'OJT Coordination',
            'Scholarship Validation',
            'Campus Tour',
            'Official Coordination / Meeting',
            'Institutional Audit / Evaluation',
            'Benchmarking / Educational Visit',
            'Campus Recruitment / Career Linkage',
            'Parent / Guardian Inquiry',
            'Document Request / Verification',
            'Guest Speaker / Resource Person',
            'Deliveries / Technical Services',
            'None',
            'Other'
        ]), 1, 1, 1, 4, '— Select Purpose of Visit —', 'Specific purpose of entry'],
        ['time_in', 'Time In', 'time', null, 1, 1, 0, 5, '', 'Entry time'],
        ['time_out', 'Time Out', 'time', null, 0, 1, 0, 6, '', 'Exit time'],
        ['signature_confirmed', 'Signature / Confirmation', 'signature_confirm', null, 1, 1, 1, 7, '', 'Verified and logged by reception'],
        ['remarks', 'Remarks / Contact No.', 'text', null, 0, 1, 0, 8, 'Mobile number or designated host', 'Optional visitor notes']
    ];
    foreach ($v_fields as $f) {
        $field_stmt->execute(array_merge([$visitor_id], $f));
    }

    // Also seed 1 Custom Logbook to prove extensibility out of the box!
    $type_stmt->execute([
        'counseling-intake',
        'Counseling Intake & Case Log',
        'Confidential intake log for structured guidance counseling sessions, psycho-social interventions, and follow-ups.',
        'bi-journal-check',
        'purple',
        0, // is_system = 0 (Custom!)
        5
    ]);
    $counseling_id = $pdo->lastInsertId();

    $ci_fields = [
        ['date', 'Date of Session', 'date', null, 1, 1, 1, 1, '', 'Intake date'],
        ['name', 'Client Name', 'text', null, 1, 1, 1, 2, 'Client Name / Code', 'Name or client alias'],
        ['client_type', 'Client Type', 'select', json_encode(['Student', 'Faculty', 'Staff', 'Parent']), 1, 1, 1, 3, '', 'Category'],
        ['referral_type', 'Referral Type', 'select', json_encode(['Self-Referred', 'Faculty Referral', 'Peer Referral', 'Disciplinary Referral']), 1, 1, 1, 4, '', 'Mode of intake'],
        ['vacant_time', 'Vacant Time', 'multiselect', json_encode([
            'Monday (Whole Day / Flexible)', 'Monday 7:30 AM - 9:00 AM', 'Monday 9:00 AM - 10:30 AM', 'Monday 10:30 AM - 12:00 PM', 'Monday 1:00 PM - 2:30 PM', 'Monday 2:30 PM - 4:00 PM', 'Monday 4:00 PM - 5:30 PM',
            'Tuesday (Whole Day / Flexible)', 'Tuesday 7:30 AM - 9:00 AM', 'Tuesday 9:00 AM - 10:30 AM', 'Tuesday 10:30 AM - 12:00 PM', 'Tuesday 1:00 PM - 2:30 PM', 'Tuesday 2:30 PM - 4:00 PM', 'Tuesday 4:00 PM - 5:30 PM',
            'Wednesday (Whole Day / Flexible)', 'Wednesday 7:30 AM - 9:00 AM', 'Wednesday 9:00 AM - 10:30 AM', 'Wednesday 10:30 AM - 12:00 PM', 'Wednesday 1:00 PM - 2:30 PM', 'Wednesday 2:30 PM - 4:00 PM', 'Wednesday 4:00 PM - 5:30 PM',
            'Thursday (Whole Day / Flexible)', 'Thursday 7:30 AM - 9:00 AM', 'Thursday 9:00 AM - 10:30 AM', 'Thursday 10:30 AM - 12:00 PM', 'Thursday 1:00 PM - 2:30 PM', 'Thursday 2:30 PM - 4:00 PM', 'Thursday 4:00 PM - 5:30 PM',
            'Friday (Whole Day / Flexible)', 'Friday 7:30 AM - 9:00 AM', 'Friday 9:00 AM - 10:30 AM', 'Friday 10:30 AM - 12:00 PM', 'Friday 1:00 PM - 2:30 PM', 'Friday 2:30 PM - 4:00 PM', 'Friday 4:00 PM - 5:30 PM',
            'MWF Morning (8:00 AM - 12:00 PM)', 'MWF Afternoon (1:00 PM - 5:00 PM)', 'TTh Morning (8:00 AM - 12:00 PM)', 'TTh Afternoon (1:00 PM - 5:00 PM)'
        ]), 1, 1, 0, 5, '— Select Vacant Weekday & Time (Select Many) —', 'Select one or more available weekdays and vacant time slots'],
        ['counselor_in_charge', 'Counselor In-Charge', 'text', null, 1, 1, 1, 6, 'Counselor name', 'Staff counselor assigned'],
        ['session_status', 'Session Status', 'select', json_encode(['Initial Intake', 'In-Progress Follow-up', 'Case Closed', 'Referred Externally']), 1, 1, 1, 7, '', 'Case progression'],
        ['remarks', 'Action Plan Summary', 'select', json_encode(['Agreed Action Plan', 'Follow Up', 'Both']), 1, 1, 1, 8, '', 'Administrative summary']
    ];
    foreach ($ci_fields as $f) {
        $field_stmt->execute(array_merge([$counseling_id], $f));
    }

    // Now seed realistic sample records for today and recent days so dashboard is immediately populated!
    seed_sample_records($pdo, $admin_id, $borrower_id, $moral_id, $client_id, $visitor_id, $counseling_id);
}

function seed_sample_records(PDO $pdo, $admin_id, $borrower_id, $moral_id, $client_id, $visitor_id, $counseling_id) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $two_days_ago = date('Y-m-d', strtotime('-2 days'));
    $three_days_ago = date('Y-m-d', strtotime('-3 days'));
    $four_days_ago = date('Y-m-d', strtotime('-4 days'));

    $rec_stmt = $pdo->prepare("INSERT INTO `logbook_records` 
        (`logbook_type_id`, `record_no`, `entry_date`, `client_name`, `client_type`, `purpose`, `status`, `notes`, `data_json`, `created_by`, `created_at`) 
        VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?)");

    // --- 1. Borrower's Log Samples ---
    $borrowers = [
        [
            'no' => 1, 'date' => $four_days_ago, 'name' => 'John Carlo Dela Cruz', 'client_type' => 'Student',
            'course' => 'BS Psychology 3-A', 'purpose' => 'Standard Progressive Matrices (SPM) Test Manual',
            'return_date' => $three_days_ago, 'status' => 'Returned', 'remarks' => 'Returned complete in good condition'
        ],
        [
            'no' => 2, 'date' => $three_days_ago, 'name' => 'Prof. Angela Gutierrez', 'client_type' => 'Faculty',
            'course' => 'College of Arts & Sciences', 'purpose' => 'Digital Projector & HDMI Cable for Mental Health Seminar',
            'return_date' => $yesterday, 'status' => 'Returned', 'remarks' => 'Returned with remote controller'
        ],
        [
            'no' => 3, 'date' => $two_days_ago, 'name' => 'Bea Rose Mendoza', 'client_type' => 'Student',
            'course' => 'BSED English 4-1', 'purpose' => '16PF Psychological Assessment Questionnaire Booklets (Set A)',
            'return_date' => '', 'status' => 'Pending', 'remarks' => 'To be returned on Friday before 4 PM'
        ],
        [
            'no' => 4, 'date' => $yesterday, 'name' => 'Mark Anthony Ramos', 'client_type' => 'Alumni',
            'course' => 'BS Criminology (Batch 2023)', 'purpose' => 'Career Guidance Reference Handbooks Vol. 2',
            'return_date' => '', 'status' => 'Pending', 'remarks' => 'Requested 2 days reading loan'
        ],
        [
            'no' => 5, 'date' => $today, 'name' => 'Liezel Joy Soriano', 'client_type' => 'Student',
            'course' => 'BS Hospitality Management 2-C', 'purpose' => 'Conference Microphone & Speaker Set',
            'return_date' => '', 'status' => 'Pending', 'remarks' => 'Borrowed for OSAS Student Council meeting'
        ]
    ];

    foreach ($borrowers as $b) {
        $json = json_encode([
            'date' => $b['date'],
            'name' => $b['name'],
            'client_type' => $b['client_type'],
            'course_year_dept' => $b['course'],
            'purpose' => $b['purpose'],
            'date_of_return' => $b['return_date'],
            'return_status' => $b['status'],
            'remarks' => $b['remarks']
        ]);
        $rec_stmt->execute([
            $borrower_id, $b['no'], $b['date'], $b['name'], $b['client_type'], $b['purpose'], $b['remarks'], $json, $admin_id, $b['date'] . ' 09:15:00'
        ]);
    }

    // --- 2. Good Moral Samples ---
    $morals = [
        [
            'no' => 1, 'date' => $four_days_ago, 'grad' => '2023', 'name' => 'Patricia Marie Santos',
            'purpose' => 'PRC/Government Exam', 'purpose_details' => 'Licensure Exam for Teachers (LET)',
            'issued' => $three_days_ago, 'remarks' => 'OR #90214 - Released'
        ],
        [
            'no' => 2, 'date' => $three_days_ago, 'grad' => '2022', 'name' => 'Joshua David Fernandez',
            'purpose' => 'Employment', 'purpose_details' => 'PNP Application requirement',
            'issued' => $two_days_ago, 'remarks' => 'OR #90235 - Released with dry seal'
        ],
        [
            'no' => 3, 'date' => $two_days_ago, 'grad' => '2024', 'name' => 'Althea Mae Valdez',
            'purpose' => 'Scholarship', 'purpose_details' => 'CHED Tulong Dunong Program',
            'issued' => $yesterday, 'remarks' => 'OR #90251 - Released'
        ],
        [
            'no' => 4, 'date' => $yesterday, 'grad' => 'Undergraduate (BSBA 2)', 'name' => 'Christian Gabriel Tan',
            'purpose' => 'Transfer', 'purpose_details' => 'Transferring to Manila State Univ',
            'issued' => $today, 'remarks' => 'Clearance signed and approved'
        ],
        [
            'no' => 5, 'date' => $today, 'grad' => '2021', 'name' => 'Janine Nicole Rivera',
            'purpose' => 'Other', 'purpose_details' => 'Visa Requirement / Overseas Work Application',
            'issued' => '', 'remarks' => 'Processing - pending verification of records'
        ]
    ];

    foreach ($morals as $m) {
        $json = json_encode([
            'date' => $m['date'],
            'year_graduated' => $m['grad'],
            'name' => $m['name'],
            'purpose' => $m['purpose'],
            'purpose_details' => $m['purpose_details'],
            'signature_confirmed' => '1',
            'date_issued' => $m['issued'],
            'remarks' => $m['remarks']
        ]);
        $rec_stmt->execute([
            $moral_id, $m['no'], $m['date'], $m['name'], 'Alumni', $m['purpose'], $m['remarks'], $json, $admin_id, $m['date'] . ' 10:00:00'
        ]);
    }

    // --- 3. Client's Log Samples ---
    $clients = [
        [
            'no' => 1, 'date' => $three_days_ago, 'name' => 'Ronalyn Castro', 'client_type' => 'Student',
            'course' => 'BS Nursing 3-A', 'purpose' => 'Walk-in Consultation', 'in' => '08:30:00', 'out' => '09:15:00',
            'remarks' => 'Consulted on stress management during clinical duties'
        ],
        [
            'no' => 2, 'date' => $three_days_ago, 'name' => 'Engr. Kenneth Pineda', 'client_type' => 'Faculty',
            'course' => 'College of Engineering', 'purpose' => 'Special Assistance', 'in' => '10:00:00', 'out' => '10:45:00',
            'remarks' => 'Inquired regarding student wellness referral process'
        ],
        [
            'no' => 3, 'date' => $two_days_ago, 'name' => 'Dexter James Uy', 'client_type' => 'Student',
            'course' => 'BS Information Technology 4-B', 'purpose' => 'Exit Interview', 'in' => '13:30:00', 'out' => '14:20:00',
            'remarks' => 'Completed graduating student exit interview form'
        ],
        [
            'no' => 4, 'date' => $yesterday, 'name' => 'Kyla Mae Corpuz', 'client_type' => 'Student',
            'course' => 'BSED Math 1-A', 'purpose' => 'Clearance Signing', 'in' => '09:40:00', 'out' => '09:55:00',
            'remarks' => 'Guidance clearance signed'
        ],
        [
            'no' => 5, 'date' => $yesterday, 'name' => 'Jerome Bautista', 'client_type' => 'Alumni',
            'course' => 'BS Accountancy (2020)', 'purpose' => 'Certificate Request', 'in' => '11:10:00', 'out' => '11:35:00',
            'remarks' => 'Requested certificate of guidance clearance for employment'
        ],
        [
            'no' => 6, 'date' => $today, 'name' => 'Samantha Gail Cruz', 'client_type' => 'Student',
            'course' => 'BS Biology 2-A', 'purpose' => 'Routine Counseling', 'in' => '08:45:00', 'out' => '09:40:00',
            'remarks' => 'Academic counseling session conducted'
        ],
        [
            'no' => 7, 'date' => $today, 'name' => 'Mrs. Corazon Villanueva', 'client_type' => 'Visitor',
            'course' => 'Parent of 1st Year Student', 'purpose' => 'Walk-in Consultation', 'in' => '10:15:00', 'out' => '',
            'remarks' => 'Conferred with counselor regarding student adjustment'
        ]
    ];

    foreach ($clients as $c) {
        $json = json_encode([
            'date' => $c['date'],
            'name' => $c['name'],
            'client_type' => $c['client_type'],
            'course_year' => $c['course'],
            'purpose' => $c['purpose'],
            'time_in' => $c['in'],
            'time_out' => $c['out'],
            'signature_confirmed' => '1',
            'remarks' => $c['remarks']
        ]);
        $rec_stmt->execute([
            $client_id, $c['no'], $c['date'], $c['name'], $c['client_type'], $c['purpose'], $c['remarks'], $json, $admin_id, $c['date'] . ' ' . $c['in']
        ]);
    }

    // --- 4. Visitor's Log Samples ---
    $visitors = [
        [
            'no' => 1, 'date' => $three_days_ago, 'name' => 'Atty. Victorio Lim',
            'agency' => 'Commission on Higher Education (CHED Reg. II)', 'purpose' => 'Institutional Quality Audit & Student Services Validation',
            'in' => '09:00:00', 'out' => '11:45:00', 'remarks' => 'Audited guidance counseling records and facilities'
        ],
        [
            'no' => 2, 'date' => $two_days_ago, 'name' => 'Gracelyn Ocampo, RSW',
            'agency' => 'DSWD Provincial Field Office', 'purpose' => 'Case Coordination & Pantawid Pamilya Scholar Assessment',
            'in' => '13:15:00', 'out' => '15:00:00', 'remarks' => 'Met with OSAS scholarship coordinator'
        ],
        [
            'no' => 3, 'date' => $yesterday, 'name' => 'Capt. Ramon Dela Rosa',
            'agency' => 'Philippine National Police - Women & Children Desk', 'purpose' => 'Campus Safety & Anti-Bullying Protocol Coordination',
            'in' => '10:30:00', 'out' => '11:30:00', 'remarks' => 'Coordinated joint seminar on youth protection'
        ],
        [
            'no' => 4, 'date' => $today, 'name' => 'Mr. Christopher Pascual',
            'agency' => 'Innovatech IT Solutions Ltd.', 'purpose' => 'Campus Recruitment & Industry Linkage Discussion',
            'in' => '09:30:00', 'out' => '10:45:00', 'remarks' => 'Discussed career fair participation'
        ]
    ];

    foreach ($visitors as $v) {
        $json = json_encode([
            'date' => $v['date'],
            'name' => $v['name'],
            'agency' => $v['agency'],
            'purpose' => $v['purpose'],
            'time_in' => $v['in'],
            'time_out' => $v['out'],
            'signature_confirmed' => '1',
            'remarks' => $v['remarks']
        ]);
        $rec_stmt->execute([
            $visitor_id, $v['no'], $v['date'], $v['name'], 'Visitor', $v['purpose'], $v['remarks'], $json, $admin_id, $v['date'] . ' ' . $v['in']
        ]);
    }

    // --- 5. Counseling Intake Samples ---
    $couns = [
        [
            'no' => 1, 'date' => $three_days_ago, 'name' => 'Client #2026-A01', 'client_type' => 'Student',
            'referral_type' => 'Self-Referred', 'vacant_time' => 'MWF 1:00 PM - 3:00 PM', 'counselor' => 'Maria Elena Santos, RGC',
            'status' => 'In-Progress Follow-up', 'remarks' => 'Follow Up'
        ],
        [
            'no' => 2, 'date' => $yesterday, 'name' => 'Client #2026-A02', 'client_type' => 'Student',
            'referral_type' => 'Faculty Referral', 'vacant_time' => 'TTh 9:00 AM - 11:00 AM', 'counselor' => 'Maria Elena Santos, RGC',
            'status' => 'Initial Intake', 'remarks' => 'Both'
        ]
    ];
    foreach ($couns as $ci) {
        $json = json_encode([
            'date' => $ci['date'],
            'name' => $ci['name'],
            'client_type' => $ci['client_type'],
            'referral_type' => $ci['referral_type'],
            'vacant_time' => $ci['vacant_time'],
            'counselor_in_charge' => $ci['counselor'],
            'session_status' => $ci['status'],
            'remarks' => $ci['remarks']
        ]);
        $rec_stmt->execute([
            $counseling_id, $ci['no'], $ci['date'], $ci['name'], $ci['client_type'], $ci['referral_type'], $ci['remarks'], $json, $admin_id, $ci['date'] . ' 14:00:00'
        ]);
    }
}
