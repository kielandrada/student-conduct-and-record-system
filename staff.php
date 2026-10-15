<?php
/**
 * staff.php  —  STAFF DASHBOARD
 * Dito nagtatala ang staff ng student conduct cases.
 * Para lang sa naka-login na may role na "staff".
 */

session_start();

// Guard
if (($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: index.php');
    exit();
}

require 'db_connect.php';

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// CSRF token: pinoprotektahan ang form laban sa pekeng submit galing sa ibang website
if (!isset($_SESSION['case_csrf_token'])) {
    $_SESSION['case_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['case_csrf_token'];

// Listahan ng students para sa dropdown
$students = $pdo->query("SELECT id, gmail, full_name FROM users WHERE role = 'student' ORDER BY gmail")->fetchAll();

// Laman ng form (ibabalik sa form kapag may error, para hindi mabura ang tinype)
$error = '';
$old = [
    'student_id'    => '',
    'case_title'    => '',
    'description'   => '',
    'incident_date' => date('Y-m-d'),
];

// ---------- Kapag na-submit ang form ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $default) {
        $old[$field] = trim($_POST[$field] ?? $default);
    }

    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $date      = DateTime::createFromFormat('!Y-m-d', $old['incident_date']);
    $dateIsValid = $date && $date->format('Y-m-d') === $old['incident_date'];

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $error = 'The form has expired. Please refresh the page and try again.';
    } elseif (!$studentId || $old['case_title'] === '' || mb_strlen($old['case_title']) > 150
              || $old['description'] === '' || !$dateIsValid) {
        $error = 'Please complete the student, case title, details, and date correctly.';
    } else {
        // Verify that the selected account is truly a student account
        $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
        $check->execute([$studentId]);

        if (!$check->fetchColumn()) {
            $error = 'Please select a valid student account.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO conduct_cases (student_id, staff_id, case_title, description, incident_date)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insert->execute([$studentId, $_SESSION['user_id'], $old['case_title'], $old['description'], $old['incident_date']]);

            // Redirect pagkatapos mag-save, para hindi madoble ang entry kapag nag-refresh
            header('Location: staff.php?saved=1');
            exit();
        }
    }
}

// ---------- Mga numero at listahan para sa dashboard ----------
$totalCases   = (int) $pdo->query('SELECT COUNT(*) FROM conduct_cases')->fetchColumn();
$monthlyCases = (int) $pdo->query("SELECT COUNT(*) FROM conduct_cases WHERE created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')")->fetchColumn();
$totalStudents = count($students);

$cases = $pdo->query(
    'SELECT c.case_title, c.description, c.incident_date,
            s.gmail AS student_email, t.gmail AS staff_email
     FROM conduct_cases AS c
     JOIN users AS s ON s.id = c.student_id
     JOIN users AS t ON t.id = c.staff_id
     ORDER BY c.created_at DESC
     LIMIT 25'
)->fetchAll();

$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Student Conduct &amp; Guidance System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">

        <!-- ============ SIDEBAR ============ -->
        <aside class="sidebar-panel">
            <nav class="sidebar-nav" aria-label="Staff navigation">
                <div class="nav-title">Student Conduct &amp; Guidance System</div>
                <a href="#dashboard"    class="nav-link active">🏠 Dashboard</a>
                <a href="#record-case"  class="nav-link">✍️ Record a Case</a>
                <a href="#case-records" class="nav-link">📋 Case Records</a>
                <a href="logout.php"    class="nav-link logout">🚪 Logout</a>
            </nav>
        </aside>

        <!-- ============ MAIN CONTENT ============ -->
        <main class="dashboard-main" id="dashboard">
            <header class="dashboard-header">
                <div class="header-title">Staff Case Management</div>
                <div class="header-user">👤 <?= $escape($_SESSION['gmail']) ?></div>
            </header>

            <section class="dashboard-content">
                <div class="staff-page-heading">
                    <div>
                        <h1>Staff Dashboard</h1>
                        <p>Record student conduct cases and review recent entries.</p>
                    </div>
                    <a class="staff-primary-link" href="#record-case">+ New Case</a>
                </div>

                <div class="summary-row">
                    <div class="summary-box">
                        <div class="summary-label">Total Cases</div>
                        <div class="summary-value"><?= $totalCases ?></div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">Recorded This Month</div>
                        <div class="summary-value"><?= $monthlyCases ?></div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">Student Accounts</div>
                        <div class="summary-value"><?= $totalStudents ?></div>
                    </div>
                </div>

                <?php if ($saved): ?>
                    <div class="staff-message staff-message-success" role="status">The student case record has been saved successfully.</div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="staff-message staff-message-error" role="alert"><?= $escape($error) ?></div>
                <?php endif; ?>

                <!-- ============ RECORD A CASE ============ -->
                <section class="staff-panel staff-form-panel" id="record-case">
                    <div class="staff-panel-heading">
                        <div>
                            <h2>Record a Student Case</h2>
                            <p>Enter the actual case title and details.</p>
                        </div>
                    </div>

                    <?php if (!$students): ?>
                        <p class="staff-empty-state">There are no student accounts in the database yet. Please add a student account before recording a case.</p>
                    <?php else: ?>
                        <form method="post" action="staff.php#record-case" class="staff-case-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

                            <div class="staff-form-grid">
                                <label class="staff-field">
                                    <span>Student</span>
                                    <select name="student_id" required>
                                        <option value="">Select a student account</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?= (int) $student['id'] ?>"
                                                <?= (string) $old['student_id'] === (string) $student['id'] ? 'selected' : '' ?>>
                                                <?= $escape($student['full_name'] ? $student['full_name'] . ' (' . $student['gmail'] . ')' : $student['gmail']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="staff-field">
                                    <span>Case / Incident Title</span>
                                    <input type="text" name="case_title" maxlength="150"
                                           value="<?= $escape($old['case_title']) ?>"
                                           placeholder="Enter the case name" required>
                                </label>

                                <label class="staff-field staff-field-full">
                                    <span>Case Details</span>
                                    <textarea name="description" rows="4"
                                              placeholder="Describe the incident, date/time, and important details"
                                              required><?= $escape($old['description']) ?></textarea>
                                </label>

                                <label class="staff-field">
                                    <span>Incident Date</span>
                                    <input type="date" name="incident_date" value="<?= $escape($old['incident_date']) ?>" required>
                                </label>
                            </div>

                            <div class="staff-form-actions">
                                <button class="staff-submit-button" type="submit">Save Case Record</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <!-- ============ CASE RECORDS ============ -->
                <section class="staff-panel staff-records-panel" id="case-records">
                    <div class="staff-panel-heading">
                        <div>
                            <h2>Recent Case Records</h2>
                            <p>Most recent 25 recorded student cases.</p>
                        </div>
                    </div>

                    <?php if (!$cases): ?>
                        <p class="staff-empty-state">No cases have been recorded yet.</p>
                    <?php else: ?>
                        <div class="staff-table-wrap">
                            <table class="staff-case-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Case</th>
                                        <th>Incident Date</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cases as $case): ?>
                                        <tr>
                                            <td><?= $escape($case['student_email']) ?></td>
                                            <td>
                                                <strong><?= $escape($case['case_title']) ?></strong>
                                                <span class="staff-case-description"><?= nl2br($escape($case['description'])) ?></span>
                                            </td>
                                            <td><?= $escape(date('M j, Y', strtotime($case['incident_date']))) ?></td>
                                            <td><?= $escape($case['staff_email']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </section>
        </main>

    </div>
</body>
</html>