<?php
require 'auth.php';
requireLogin('admin', ['school_head']);

require 'db_connect.php';
require 'audit.php';

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$actorId = (int) $_SESSION['user_id'];
$error = '';
$saved = isset($_GET['saved']);

if (!isset($_SESSION['case_csrf_token'])) {
    $_SESSION['case_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['case_csrf_token'];
$postString = static fn (string $key): string =>
    isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $caseId = filter_var($postString('case_id'), FILTER_VALIDATE_INT);
    $startDateValue = $postString('start_date');
    $endDateValue = $postString('end_date');
    $startDate = DateTime::createFromFormat('!Y-m-d', $startDateValue);
    $endDate = DateTime::createFromFormat('!Y-m-d', $endDateValue);
    $datesAreValid = $startDate && $startDate->format('Y-m-d') === $startDateValue
        && $endDate && $endDate->format('Y-m-d') === $endDateValue
        && $endDate >= $startDate;

    if (!hash_equals($csrfToken, $postString('csrf_token'))) {
        $error = 'The form has expired. Please refresh the page and try again.';
    } elseif ($postString('action') !== 'sign_suspension' || !$caseId || !$datesAreValid) {
        $error = 'Choose a valid violation and suspension date range.';
    } else {
        $caseCheck = $pdo->prepare(
            'SELECT c.id FROM conduct_cases AS c
             LEFT JOIN suspension_signatures AS ss ON ss.case_id = c.id
             WHERE c.id = ? AND ss.id IS NULL'
        );
        $caseCheck->execute([$caseId]);
        if (!$caseCheck->fetchColumn()) {
            $error = 'That record does not exist or already has a signed suspension.';
        } else {
            $pdo->beginTransaction();
            $signature = $pdo->prepare(
                'INSERT INTO suspension_signatures (case_id, start_date, end_date, signed_by)
                 VALUES (?, ?, ?, ?)'
            );
            $signature->execute([$caseId, $startDateValue, $endDateValue, $actorId]);
            $signatureId = (int) $pdo->lastInsertId();
            recordAuditEvent(
                $pdo,
                $actorId,
                'suspension_signed',
                'suspension',
                $signatureId,
                'Suspension signed for violation record ' . $caseId
            );
            $pdo->commit();
            header('Location: admin.php?saved=1#audit-logs');
            exit();
        }
    }
}

$monthlyViolations = (int) $pdo->query(
    "SELECT COUNT(*) FROM conduct_cases
     WHERE created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
       AND created_at < DATE_ADD(DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01'), INTERVAL 1 MONTH)"
)->fetchColumn();
$totalViolations = (int) $pdo->query('SELECT COUNT(*) FROM conduct_cases')->fetchColumn();

$cases = $pdo->query(
    'SELECT c.id, c.case_title, c.description, c.consequence, c.incident_date, c.created_at,
            s.gmail AS student_email, s.full_name AS student_name,
            t.gmail AS staff_email, t.full_name AS staff_name,
            ss.start_date AS suspension_start, ss.end_date AS suspension_end,
            ss.signed_at, signer.gmail AS signer_email, signer.full_name AS signer_name
     FROM conduct_cases AS c
     JOIN users AS s ON s.id = c.student_id
     JOIN users AS t ON t.id = c.staff_id
     LEFT JOIN suspension_signatures AS ss ON ss.case_id = c.id
     LEFT JOIN users AS signer ON signer.id = ss.signed_by
     ORDER BY c.created_at DESC'
)->fetchAll();

$auditQuery = $pdo->prepare(
    'SELECT a.action, a.entity_type, a.entity_id, a.details, a.created_at,
            u.gmail AS actor_email, u.full_name AS actor_name, u.staff_category
     FROM audit_logs AS a
     JOIN users AS u ON u.id = a.actor_id
     WHERE a.actor_id <> ?
     ORDER BY a.created_at DESC'
);
$auditQuery->execute([$actorId]);
$auditLogs = $auditQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Head Review | Student Conduct &amp; Guidance System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <aside class="sidebar-panel">
            <nav class="sidebar-nav" aria-label="School Head navigation">
                <div class="nav-title">Student Conduct &amp; Guidance System</div>
                <a href="#dashboard" class="nav-link active">🏠 Dashboard</a>
                <a href="#case-records" class="nav-link">📋 Review Violations</a>
                <a href="#audit-logs" class="nav-link">🧾 Staff Audit Logs</a>
                <a href="logout.php" class="nav-link logout">🚪 Logout</a>
            </nav>
        </aside>

        <main class="dashboard-main" id="dashboard">
            <header class="dashboard-header">
                <div class="header-title">School Head Review</div>
                <div class="header-user">👤 <?= $escape($_SESSION['gmail'] ?? '') ?></div>
            </header>

            <section class="dashboard-content">
                <div class="staff-page-heading">
                    <div>
                        <h1>Principal Dashboard</h1>
                        <p>Review violation records, authorize suspensions, and monitor staff activity.</p>
                    </div>
                </div>

                <div class="summary-row">
                    <div class="summary-box">
                        <div class="summary-label">Violations This Month</div>
                        <div class="summary-value"><?= $monthlyViolations ?></div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">All Violation Records</div>
                        <div class="summary-value"><?= $totalViolations ?></div>
                    </div>
                </div>

                <?php if ($saved): ?>
                    <div class="staff-message staff-message-success" role="status">Suspension signed and added to the audit log.</div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="staff-message staff-message-error" role="alert"><?= $escape($error) ?></div>
                <?php endif; ?>

                <section class="staff-panel staff-records-panel" id="case-records">
                    <div class="staff-panel-heading">
                        <h2>Review Violation Records</h2>
                        <p>Signed suspensions show the principal account and signature time.</p>
                    </div>
                    <?php if (!$cases): ?>
                        <p class="staff-empty-state">No violation records are available for review.</p>
                    <?php else: ?>
                        <div class="staff-table-wrap">
                            <table class="staff-case-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Violation and Consequence</th>
                                        <th>Incident</th>
                                        <th>Recorded By</th>
                                        <th>Suspension Review / Signature</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cases as $case): ?>
                                        <tr>
                                            <td><?= $escape($case['student_name'] ?: $case['student_email']) ?>
                                                <span class="staff-case-description"><?= $escape($case['student_email']) ?></span>
                                            </td>
                                            <td>
                                                <strong><?= $escape($case['case_title']) ?></strong>
                                                <span class="staff-case-description"><?= nl2br($escape($case['description'])) ?></span>
                                                <span class="staff-case-description"><strong>Consequence:</strong>
                                                    <?= nl2br($escape($case['consequence'])) ?></span>
                                            </td>
                                            <td><?= $escape(date('M j, Y', strtotime($case['incident_date']))) ?></td>
                                            <td><?= $escape($case['staff_name'] ?: $case['staff_email']) ?></td>
                                            <td>
                                                <?php if ($case['signed_at']): ?>
                                                    <strong>Suspension signed</strong>
                                                    <span class="staff-case-description">
                                                        <?= $escape(date('M j, Y', strtotime($case['suspension_start']))) ?>
                                                        – <?= $escape(date('M j, Y', strtotime($case['suspension_end']))) ?>
                                                    </span>
                                                    <span class="staff-case-description">
                                                        By <?= $escape($case['signer_name'] ?: $case['signer_email']) ?>,
                                                        <?= $escape(date('M j, Y g:i A', strtotime($case['signed_at']))) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <form method="post" action="admin.php#case-records" class="staff-case-form">
                                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="sign_suspension">
                                                        <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                                                        <label class="staff-field">
                                                            <span>Suspension starts</span>
                                                            <input type="date" name="start_date" required>
                                                        </label>
                                                        <label class="staff-field">
                                                            <span>Suspension ends</span>
                                                            <input type="date" name="end_date" required>
                                                        </label>
                                                        <button class="staff-submit-button" type="submit">Sign Suspension</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="staff-panel staff-records-panel" id="audit-logs">
                    <div class="staff-panel-heading">
                        <h2>Staff Audit Logs</h2>
                        <p>Recent actions by other staff accounts. Private guidance note contents are never included.</p>
                    </div>
                    <?php if (!$auditLogs): ?>
                        <p class="staff-empty-state">No activity by other staff has been logged yet.</p>
                    <?php else: ?>
                        <div class="staff-table-wrap">
                            <table class="staff-case-table">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Staff</th>
                                        <th>Action</th>
                                        <th>Record</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td><?= $escape(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
                                            <td>
                                                <?= $escape($log['actor_name'] ?: $log['actor_email']) ?>
                                                <span class="staff-case-description">
                                                    <?= $escape(STAFF_CATEGORIES[$log['staff_category'] ?? ''][0] ?? $log['staff_category'] ?? 'Staff') ?>
                                                </span>
                                            </td>
                                            <td><?= $escape(ucwords(str_replace('_', ' ', $log['action']))) ?></td>
                                            <td><?= $escape(ucwords(str_replace('_', ' ', $log['entity_type']))) ?>
                                                #<?= (int) $log['entity_id'] ?></td>
                                            <td><?= $escape($log['details']) ?></td>
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
