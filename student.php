<?php
/**
 * student.php  —  STUDENT DASHBOARD
 * Para lang sa naka-login na may role na "student".
 */

session_start();

// Guard: kung hindi student ang naka-login, ibalik sa login page
if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: index.php');
    exit();
}

require 'db_connect.php';

$studentId = (int) $_SESSION['user_id'];

// Maikling pangalan ng function para sa htmlspecialchars (proteksyon laban sa XSS)
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// ---------- 1. Profile ng student ----------
$stmt = $pdo->prepare('SELECT gmail, full_name, role, created_at FROM users WHERE id = ?');
$stmt->execute([$studentId]);
$profile = $stmt->fetch();

if (!$profile) {                      // na-delete na pala ang account
    header('Location: logout.php');
    exit();
}
$displayName = $profile['full_name'] ?: $profile['gmail'];

// ---------- 2. Bilang ng cases (total at ngayong buwan) ----------
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')), 0) AS this_month
     FROM conduct_cases
     WHERE student_id = ?"
);
$stmt->execute([$studentId]);
$counts = $stmt->fetch();

// ---------- 3. Listahan ng cases ----------
$stmt = $pdo->prepare(
    'SELECT case_title, description, incident_date
     FROM conduct_cases
     WHERE student_id = ?
     ORDER BY created_at DESC'
);
$stmt->execute([$studentId]);
$cases = $stmt->fetchAll();
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
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">

        <!-- ============ SIDEBAR ============ -->
        <aside class="sidebar-panel">
            <nav class="sidebar-nav" aria-label="Student navigation">
                <div class="nav-title">Student Conduct &amp; Guidance System</div>
                <!-- Ang href="#id" ay lilipat sa section na may kaparehong id sa baba -->
                <a href="#dashboard" class="nav-link active">🏠 Dashboard</a>
                <a href="#profile"   class="nav-link">👤 Profile</a>
                <a href="#my-cases"  class="nav-link">📋 My Cases</a>
                <a href="#alerts"    class="nav-link">🔔 Alerts</a>
                <a href="#notices"   class="nav-link">📢 Notices</a>
                <a href="logout.php" class="nav-link logout">🚪 Logout</a>
            </nav>
        </aside>

        <!-- ============ MAIN CONTENT ============ -->
        <main class="dashboard-main" id="dashboard">
            <header class="dashboard-header">
                <div class="header-title">Student Conduct &amp; Guidance System</div>
                <div class="header-user">👤 <?= $escape($displayName) ?></div>
            </header>

            <section class="dashboard-content">
                <h2>Welcome, <?= $escape($displayName) ?>!</h2>

                <div class="summary-row">
                    <div class="summary-box">
                        <div class="summary-label">My Cases</div>
                        <div class="summary-value"><?= (int) $counts['total'] ?></div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">Recorded This Month</div>
                        <div class="summary-value"><?= (int) $counts['this_month'] ?></div>
                    </div>
                </div>

                <!-- PROFILE -->
                <div class="records-panel" id="profile">
                    <h3>My Profile</h3>
                    <ul class="record-list">
                        <li><span class="record-name">Name</span> <?= $escape($profile['full_name'] ?: '—') ?></li>
                        <li><span class="record-name">Email</span> <?= $escape($profile['gmail']) ?></li>
                        <li><span class="record-name">Role</span> <?= $escape(ucfirst($profile['role'])) ?></li>
                        <li><span class="record-name">Account created</span> <?= $escape(date('M j, Y', strtotime($profile['created_at']))) ?></li>
                    </ul>
                </div>

                <!-- MY CASES -->
                <div class="records-panel" id="my-cases">
                    <h3>My Conduct Cases</h3>
                    <ul class="record-list">
                        <?php if (!$cases): ?>
                            <li><span class="record-name">Wala pang naitatalang case.</span></li>
                        <?php endif; ?>

                        <?php foreach ($cases as $case): ?>
                            <li class="student-case-row">
                                <div class="student-case-copy">
                                    <span class="record-name"><?= $escape($case['case_title']) ?></span>
                                    <p><?= nl2br($escape($case['description'])) ?></p>
                                </div>
                                <span class="record-date"><?= $escape(date('M j, Y', strtotime($case['incident_date']))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- ALERTS (placeholder — dito ilalagay ang data kapag may alerts table na) -->
                <div class="records-panel" id="alerts">
                    <h3>Alerts</h3>
                    <ul class="record-list">
                        <li><span class="record-name">Walang bagong alert.</span></li>
                    </ul>
                </div>

                <!-- NOTICES (placeholder) -->
                <div class="records-panel" id="notices">
                    <h3>Notices</h3>
                    <ul class="record-list">
                        <li><span class="record-name">Walang bagong notice.</span></li>
                    </ul>
                </div>
            </section>
        </main>

    </div>
</body>
</html>