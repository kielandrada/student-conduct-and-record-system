<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

require 'db_connect.php';

$studentId = (int) $_SESSION['user_id'];
$studentName = htmlspecialchars($_SESSION['gmail'], ENT_QUOTES, 'UTF-8');
$caseCount = 0;
$monthlyCaseCount = 0;
$studentCases = [];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

if ($pdo->query("SHOW TABLES LIKE 'conduct_cases'")->fetchColumn()) {
    $caseCountQuery = $pdo->prepare('SELECT COUNT(*) FROM conduct_cases WHERE student_id = ?');
    $caseCountQuery->execute([$studentId]);
    $caseCount = (int) $caseCountQuery->fetchColumn();

    $monthlyCountQuery = $pdo->prepare("SELECT COUNT(*) FROM conduct_cases WHERE student_id = ? AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')");
    $monthlyCountQuery->execute([$studentId]);
    $monthlyCaseCount = (int) $monthlyCountQuery->fetchColumn();

    $studentCaseQuery = $pdo->prepare('SELECT case_title, description, incident_date FROM conduct_cases WHERE student_id = ? ORDER BY created_at DESC LIMIT 10');
    $studentCaseQuery->execute([$studentId]);
    $studentCases = $studentCaseQuery->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <aside class="sidebar-panel">
            <nav class="sidebar-nav">
                <div class="nav-title">Student Conduct &amp; Guidance System</div>
                <a href="#" class="nav-link active">🏠 Dashboard</a>
                <a href="#" class="nav-link">👤 Profile</a>
                <a href="#" class="nav-link">📋 My Cases</a>
                <a href="#" class="nav-link">🔔 Alerts</a>
                <a href="#" class="nav-link">📢 Notices</a>
                <a href="logout.php" class="nav-link logout">🚪 Logout</a>
            </nav>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div class="header-title">Student Conduct &amp; Guidance System</div>
                <div class="header-user">👤 <?= $studentName ?></div>
            </header>

            <section class="dashboard-content">
                <h2>Welcome, <?= $studentName ?>!</h2>

                <div class="summary-row">
                    <div class="summary-box">
                        <div class="summary-label">My Cases</div>
                        <div class="summary-value"><?= $caseCount ?></div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">Recorded This Month</div>
                        <div class="summary-value"><?= $monthlyCaseCount ?></div>
                    </div>
                </div>

                <div class="records-panel">
                    <h3>My Conduct Cases</h3>
                    <ul class="record-list">
                        <?php if (!$studentCases): ?>
                            <li><span class="record-name">Wala pang naitatalang case.</span></li>
                        <?php else: ?>
                            <?php foreach ($studentCases as $case): ?>
                                <li class="student-case-row">
                                    <div class="student-case-copy">
                                        <span class="record-name"><?= $escape($case['case_title']) ?></span>
                                        <p><?= nl2br($escape($case['description'])) ?></p>
                                    </div>
                                    <span class="record-date"><?= $escape(date('M j, Y', strtotime($case['incident_date']))) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>
        </main>
    </div>

    <script src="js/student.js"></script>
</body>
</html>

