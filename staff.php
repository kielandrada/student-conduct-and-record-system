<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
	header('Location: index.php');
	exit();
}

require 'db_connect.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS conduct_cases (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	student_id INT NOT NULL,
	staff_id INT NOT NULL,
	case_title VARCHAR(150) NOT NULL,
	description TEXT NOT NULL,
	incident_date DATE NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_conduct_cases_student (student_id),
	KEY idx_conduct_cases_created (created_at),
	CONSTRAINT fk_conduct_cases_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE RESTRICT,
	CONSTRAINT fk_conduct_cases_staff FOREIGN KEY (staff_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!isset($_SESSION['case_csrf_token'])) {
	$_SESSION['case_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['case_csrf_token'];
$error = '';
$students = $pdo->query("SELECT id, gmail FROM users WHERE role = 'student' ORDER BY gmail")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
	$caseTitle = trim($_POST['case_title'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$incidentDate = trim($_POST['incident_date'] ?? '');
	$date = DateTime::createFromFormat('!Y-m-d', $incidentDate);

	if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
		$error = 'Nag-expire ang form. I-refresh ang page at subukan muli.';
	} elseif (!$studentId || $caseTitle === '' || strlen($caseTitle) > 150 || $description === '' || !$date || $date->format('Y-m-d') !== $incidentDate) {
		$error = 'Pakikumpleto nang tama ang student, case title, detalye, at petsa.';
	} else {
		$studentCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
		$studentCheck->execute([$studentId]);

		if (!$studentCheck->fetchColumn()) {
			$error = 'Pumili ng valid na student account.';
		} else {
			$insert = $pdo->prepare('INSERT INTO conduct_cases (student_id, staff_id, case_title, description, incident_date) VALUES (?, ?, ?, ?, ?)');
			$insert->execute([$studentId, $_SESSION['user_id'], $caseTitle, $description, $incidentDate]);
			header('Location: staff.php?saved=1');
			exit();
		}
	}
}

$totalCases = (int) $pdo->query('SELECT COUNT(*) FROM conduct_cases')->fetchColumn();
$monthlyCases = (int) $pdo->query("SELECT COUNT(*) FROM conduct_cases WHERE created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')")->fetchColumn();
$totalStudents = count($students);
$caseQuery = $pdo->query('SELECT cases.case_title, cases.description, cases.incident_date, cases.created_at, students.gmail AS student_email, staff.gmail AS staff_email FROM conduct_cases AS cases JOIN users AS students ON students.id = cases.student_id JOIN users AS staff ON staff.id = cases.staff_id ORDER BY cases.created_at DESC LIMIT 25');
$cases = $caseQuery->fetchAll(PDO::FETCH_ASSOC);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
		<aside class="sidebar-panel">
			<nav class="sidebar-nav" aria-label="Staff navigation">
				<div class="nav-title">Student Conduct &amp; Guidance System</div>
				<a href="#dashboard" class="nav-link active">🏠 Dashboard</a>
				<a href="#record-case" class="nav-link">✍️ Record a Case</a>
				<a href="#case-records" class="nav-link">📋 Case Records</a>
				<a href="logout.php" class="nav-link logout">🚪 Logout</a>
			</nav>
		</aside>

		<main class="dashboard-main" id="dashboard">
			<header class="dashboard-header">
				<div class="header-title">Staff Case Management</div>
				<div class="header-user">👤 <?= $escape($_SESSION['gmail'] ?? 'Staff') ?></div>
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
					<div class="staff-message staff-message-success" role="status">Na-save na ang student case record.</div>
				<?php endif; ?>
				<?php if ($error !== ''): ?>
					<div class="staff-message staff-message-error" role="alert"><?= $escape($error) ?></div>
				<?php endif; ?>

				<section class="staff-panel staff-form-panel" id="record-case">
					<div class="staff-panel-heading">
						<div>
							<h2>Record a Student Case</h2>
							<p>Ilagay ang aktuwal na case title at detalye. Walang slip category.</p>
						</div>
					</div>

					<?php if (!$students): ?>
						<p class="staff-empty-state">Wala pang student account sa database. Magdagdag muna ng student account bago magtala ng case.</p>
					<?php else: ?>
						<form method="post" action="staff.php#record-case" class="staff-case-form">
							<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
							<div class="staff-form-grid">
								<label class="staff-field">
									<span>Student</span>
									<select name="student_id" required>
										<option value="">Pumili ng student account</option>
										<?php foreach ($students as $student): ?>
											<option value="<?= (int) $student['id'] ?>" <?= (string) ($_POST['student_id'] ?? '') === (string) $student['id'] ? 'selected' : '' ?>><?= $escape($student['gmail']) ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="staff-field">
									<span>Case / Incident Title</span>
									<input type="text" name="case_title" maxlength="150" value="<?= $escape($_POST['case_title'] ?? '') ?>" placeholder="I-type ang pangalan ng case" required>
								</label>
								<label class="staff-field staff-field-full">
									<span>Case Details</span>
									<textarea name="description" rows="4" placeholder="Ilarawan ang insidente, petsa/oras, at mahahalagang detalye" required><?= $escape($_POST['description'] ?? '') ?></textarea>
								</label>
								<label class="staff-field">
									<span>Incident Date</span>
									<input type="date" name="incident_date" value="<?= $escape($_POST['incident_date'] ?? date('Y-m-d')) ?>" required>
								</label>
							</div>
							<div class="staff-form-actions">
								<button class="staff-submit-button" type="submit">Save Case Record</button>
							</div>
						</form>
					<?php endif; ?>
				</section>

				<section class="staff-panel staff-records-panel" id="case-records">
					<div class="staff-panel-heading">
						<div>
							<h2>Recent Case Records</h2>
							<p>Pinakabagong 25 na naitalang student cases.</p>
						</div>
					</div>
					<?php if (!$cases): ?>
						<p class="staff-empty-state">Wala pang naitatalang case.</p>
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
