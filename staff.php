<?php
require 'auth.php';
requireLogin('staff', ['discipline_committee', 'guidance_counselor']);

require 'db_connect.php';
require 'audit.php';

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$category = $_SESSION['staff_category'];
$isDisciplineCommittee = $category === 'discipline_committee';
$staffCategoryLabel = STAFF_CATEGORIES[$category][0];
$actorId = (int) $_SESSION['user_id'];

if (!isset($_SESSION['case_csrf_token'])) {
    $_SESSION['case_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['case_csrf_token'];
$error = '';
$saved = $_GET['saved'] ?? '';
$old = [
    'student_id' => '',
    'case_title' => '',
    'description' => '',
    'consequence' => '',
    'incident_date' => date('Y-m-d'),
];

$postString = static fn (string $key, string $fallback = ''): string =>
    isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $fallback;
$editCaseId = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && $postString('action') === 'update_case')
    ? filter_var($postString('case_id'), FILTER_VALIDATE_INT)
    : filter_var(is_string($_GET['edit'] ?? null) ? $_GET['edit'] : '', FILTER_VALIDATE_INT);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $postString('action');

    if (!hash_equals($csrfToken, $postString('csrf_token'))) {
        $error = 'The form has expired. Please refresh the page and try again.';
    } elseif ($isDisciplineCommittee && in_array($action, ['create_case', 'update_case'], true)) {
        foreach ($old as $field => $default) {
            $old[$field] = $postString($field, $default);
        }

        $studentId = filter_var($old['student_id'], FILTER_VALIDATE_INT);
        $date = DateTime::createFromFormat('!Y-m-d', $old['incident_date']);
        $dateIsValid = $date && $date->format('Y-m-d') === $old['incident_date'];
        $caseId = filter_var($postString('case_id'), FILTER_VALIDATE_INT);

        if (!$studentId || $old['case_title'] === '' || mb_strlen($old['case_title']) > 150
            || $old['description'] === '' || !$dateIsValid
            || $old['consequence'] === '' || mb_strlen($old['consequence']) > 5000) {
            $error = 'Complete the student, violation, details, consequence, and date correctly.';
        } else {
            $studentCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
            $studentCheck->execute([$studentId]);

            if (!$studentCheck->fetchColumn()) {
                $error = 'Please select a valid student account.';
            } elseif ($action === 'update_case' && !$caseId) {
                $error = 'Please select a valid violation record to update.';
            } else {
                $pdo->beginTransaction();
                if ($action === 'create_case') {
                    $insert = $pdo->prepare(
                        'INSERT INTO conduct_cases
                            (student_id, staff_id, case_title, description, consequence, incident_date)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $studentId,
                        $actorId,
                        $old['case_title'],
                        $old['description'],
                        $old['consequence'],
                        $old['incident_date'],
                    ]);
                    $savedCaseId = (int) $pdo->lastInsertId();
                    recordAuditEvent($pdo, $actorId, 'recorded', 'conduct_case', $savedCaseId, 'Violation recorded');
                    $pdo->commit();
                    header('Location: staff.php?saved=created#case-records');
                    exit();
                }

                $update = $pdo->prepare(
                    'UPDATE conduct_cases
                     SET student_id = ?, case_title = ?, description = ?, consequence = ?, incident_date = ?
                     WHERE id = ? AND staff_id = ?'
                );
                $update->execute([
                    $studentId,
                    $old['case_title'],
                    $old['description'],
                    $old['consequence'],
                    $old['incident_date'],
                    $caseId,
                    $actorId,
                ]);
                if ($update->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT id FROM conduct_cases WHERE id = ? AND staff_id = ?');
                    $exists->execute([$caseId, $actorId]);
                    if (!$exists->fetchColumn()) {
                        $pdo->rollBack();
                        $error = 'You can only update violation records that you created.';
                    } else {
                        recordAuditEvent($pdo, $actorId, 'updated', 'conduct_case', $caseId, 'Violation record updated');
                        $pdo->commit();
                        header('Location: staff.php?saved=updated#case-records');
                        exit();
                    }
                } else {
                    recordAuditEvent($pdo, $actorId, 'updated', 'conduct_case', $caseId, 'Violation record updated');
                    $pdo->commit();
                    header('Location: staff.php?saved=updated#case-records');
                    exit();
                }
            }
        }
    } elseif (!$isDisciplineCommittee && $action === 'save_guidance_note') {
        $caseId = filter_var($postString('case_id'), FILTER_VALIDATE_INT);
        $notes = $postString('notes');
        if (!$caseId || $notes === '' || mb_strlen($notes) > 10000) {
            $error = 'Enter a note of up to 10,000 characters for a valid record.';
        } else {
            $caseCheck = $pdo->prepare('SELECT id FROM conduct_cases WHERE id = ?');
            $caseCheck->execute([$caseId]);
            if (!$caseCheck->fetchColumn()) {
                $error = 'That violation record could not be found.';
            } else {
                $pdo->beginTransaction();
                $existingNote = $pdo->prepare(
                    'SELECT id FROM guidance_notes WHERE case_id = ? AND counselor_id = ?'
                );
                $existingNote->execute([$caseId, $actorId]);
                $noteId = $existingNote->fetchColumn();

                if ($noteId) {
                    $saveNote = $pdo->prepare('UPDATE guidance_notes SET notes = ? WHERE id = ?');
                    $saveNote->execute([$notes, $noteId]);
                    $noteAction = 'updated';
                } else {
                    $saveNote = $pdo->prepare(
                        'INSERT INTO guidance_notes (case_id, counselor_id, notes) VALUES (?, ?, ?)'
                    );
                    $saveNote->execute([$caseId, $actorId, $notes]);
                    $noteAction = 'added';
                }
                recordAuditEvent(
                    $pdo,
                    $actorId,
                    'guidance_note_' . $noteAction,
                    'guidance_note',
                    $caseId,
                    'Counselor private note ' . $noteAction
                );
                $pdo->commit();
                header('Location: staff.php?saved=note#case-records');
                exit();
            }
        }
    } else {
        $error = 'You are not allowed to perform that action.';
    }
}

$students = $isDisciplineCommittee
    ? $pdo->query("SELECT id, gmail, full_name FROM users WHERE role = 'student' ORDER BY gmail")->fetchAll()
    : [];

$editCaseId = $isDisciplineCommittee ? $editCaseId : false;
if ($editCaseId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editQuery = $pdo->prepare(
        'SELECT student_id, case_title, description, consequence, incident_date
         FROM conduct_cases WHERE id = ? AND staff_id = ?'
    );
    $editQuery->execute([$editCaseId, $actorId]);
    $editCase = $editQuery->fetch();
    if ($editCase) {
        $old = array_map(static fn ($value): string => (string) $value, $editCase);
    } else {
        $editCaseId = false;
        $error = 'You can only update violation records that you created.';
    }
}

$caseQuery = $pdo->prepare(
    'SELECT c.id, c.staff_id, c.case_title, c.description, c.consequence, c.incident_date, c.created_at, c.updated_at,
            s.gmail AS student_email, s.full_name AS student_name,
            t.gmail AS staff_email, t.full_name AS staff_name,
            gn.notes AS private_note
     FROM conduct_cases AS c
     JOIN users AS s ON s.id = c.student_id
     JOIN users AS t ON t.id = c.staff_id
     LEFT JOIN guidance_notes AS gn
       ON gn.case_id = c.id AND gn.counselor_id = ?
     ORDER BY c.created_at DESC'
);
$caseQuery->execute([$isDisciplineCommittee ? 0 : $actorId]);
$cases = $caseQuery->fetchAll();

$countQuery = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')), 0) AS monthly
     FROM conduct_cases"
);
$caseCounts = $countQuery->fetch();

$savedMessages = [
    'created' => 'Violation and consequence recorded.',
    'updated' => 'Violation record updated.',
    'note' => 'Your private note was saved.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($staffCategoryLabel) ?> | Student Conduct &amp; Guidance System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <aside class="sidebar-panel">
            <nav class="sidebar-nav" aria-label="<?= $escape($staffCategoryLabel) ?> navigation">
                <div class="nav-title">Student Conduct &amp; Guidance System</div>
                <a href="#dashboard" class="nav-link active">🏠 Dashboard</a>
                <?php if ($isDisciplineCommittee): ?>
                    <a href="#record-case" class="nav-link">✍️ Record Violation</a>
                <?php endif; ?>
                <a href="#case-records" class="nav-link">📋 Violation Records</a>
                <a href="logout.php" class="nav-link logout">🚪 Logout</a>
            </nav>
        </aside>

        <main class="dashboard-main" id="dashboard">
            <header class="dashboard-header">
                <div class="header-title"><?= $escape($staffCategoryLabel) ?> Dashboard</div>
                <div class="header-user">👤 <?= $escape($_SESSION['gmail']) ?></div>
            </header>

            <section class="dashboard-content">
                <div class="staff-page-heading">
                    <div>
                        <h1><?= $escape($staffCategoryLabel) ?></h1>
                        <p><?= $isDisciplineCommittee
                            ? 'Record student violations and assign consequences.'
                            : 'Review violations and keep private counseling notes.' ?></p>
                    </div>
                    <?php if ($isDisciplineCommittee): ?>
                        <a class="staff-primary-link" href="#record-case">+ Record Violation</a>
                    <?php endif; ?>
                </div>

                <div class="summary-row">
                    <div class="summary-box">
                        <div class="summary-label">Total Violations</div>
                        <div class="summary-value"><?= (int) $caseCounts['total'] ?></div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">Violations This Month</div>
                        <div class="summary-value"><?= (int) $caseCounts['monthly'] ?></div>
                    </div>
                </div>

                <?php if (isset($savedMessages[$saved])): ?>
                    <div class="staff-message staff-message-success" role="status"><?= $escape($savedMessages[$saved]) ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="staff-message staff-message-error" role="alert"><?= $escape($error) ?></div>
                <?php endif; ?>

                <?php if ($isDisciplineCommittee): ?>
                    <section class="staff-panel staff-form-panel" id="record-case">
                        <div class="staff-panel-heading">
                            <h2><?= $editCaseId ? 'Update Violation' : 'Record a Student Violation' ?></h2>
                            <p>Document the incident and the consequence assigned by the committee.</p>
                        </div>
                        <form method="post" action="staff.php#record-case" class="staff-case-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="<?= $editCaseId ? 'update_case' : 'create_case' ?>">
                            <?php if ($editCaseId): ?>
                                <input type="hidden" name="case_id" value="<?= (int) $editCaseId ?>">
                            <?php endif; ?>
                            <div class="staff-form-grid">
                                <label class="staff-field">
                                    <span>Student</span>
                                    <select name="student_id" required>
                                        <option value="">Select a student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?= (int) $student['id'] ?>"
                                                <?= (string) $old['student_id'] === (string) $student['id'] ? 'selected' : '' ?>>
                                                <?= $escape($student['full_name']
                                                    ? $student['full_name'] . ' (' . $student['gmail'] . ')'
                                                    : $student['gmail']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="staff-field">
                                    <span>Violation</span>
                                    <input type="text" name="case_title" maxlength="150"
                                        value="<?= $escape($old['case_title']) ?>" required>
                                </label>
                                <label class="staff-field staff-field-full">
                                    <span>Incident Details</span>
                                    <textarea name="description" rows="4" required><?= $escape($old['description']) ?></textarea>
                                </label>
                                <label class="staff-field staff-field-full">
                                    <span>Assigned Consequence</span>
                                    <textarea name="consequence" rows="3" maxlength="5000" required><?= $escape($old['consequence']) ?></textarea>
                                </label>
                                <label class="staff-field">
                                    <span>Incident Date</span>
                                    <input type="date" name="incident_date" value="<?= $escape($old['incident_date']) ?>" required>
                                </label>
                            </div>
                            <div class="staff-form-actions">
                                <button class="staff-submit-button" type="submit">
                                    <?= $editCaseId ? 'Save Changes' : 'Record Violation' ?>
                                </button>
                                <?php if ($editCaseId): ?>
                                    <a class="staff-primary-link" href="staff.php#case-records">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="staff-panel staff-records-panel" id="case-records">
                    <div class="staff-panel-heading">
                        <h2><?= $isDisciplineCommittee ? 'Violation Records' : 'Records for Counseling Review' ?></h2>
                        <p><?= $isDisciplineCommittee
                            ? 'Recent violations. You can update records you created.'
                            : 'Your notes below are private to your Guidance Counselor account.' ?></p>
                    </div>
                    <?php if (!$cases): ?>
                        <p class="staff-empty-state">No violation records have been created yet.</p>
                    <?php else: ?>
                        <div class="staff-table-wrap">
                            <table class="staff-case-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Violation and Consequence</th>
                                        <th>Incident Date</th>
                                        <th>Recorded By</th>
                                        <?php if (!$isDisciplineCommittee): ?><th>Private Counselor Note</th><?php endif; ?>
                                        <?php if ($isDisciplineCommittee): ?><th>Action</th><?php endif; ?>
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
                                            <?php if (!$isDisciplineCommittee): ?>
                                                <td>
                                                    <form method="post" action="staff.php#case-records" class="staff-case-form">
                                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="save_guidance_note">
                                                        <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                                                        <label class="staff-field">
                                                            <span>Your private note</span>
                                                            <textarea name="notes" rows="3" maxlength="10000"><?= $escape($case['private_note'] ?? '') ?></textarea>
                                                        </label>
                                                        <button class="staff-submit-button" type="submit">Save Private Note</button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                            <?php if ($isDisciplineCommittee): ?>
                                                <td>
                                                    <?php if ((int) $case['staff_id'] === $actorId): ?>
                                                        <a class="staff-primary-link" href="staff.php?edit=<?= (int) $case['id'] ?>#record-case">Edit</a>
                                                    <?php else: ?>
                                                        <span>View only</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
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
