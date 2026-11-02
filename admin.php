<?php
session_start();

if (!in_array($_SESSION['role'] ?? '', ['school_head', 'admin'], true)) {
    header('Location: index.php');
    exit();
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Head Portal | Student Conduct &amp; Guidance System</title>
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
                <a href="logout.php" class="nav-link logout">🚪 Logout</a>
            </nav>
        </aside>

        <main class="dashboard-main" id="dashboard">
            <header class="dashboard-header">
                <div class="header-title">School Head Portal</div>
                <div class="header-user">👤 <?= $escape($_SESSION['gmail'] ?? '') ?></div>
            </header>

            <section class="dashboard-content">
                <h2>Welcome, School Head</h2>
                <p>You are signed in to the Student Conduct &amp; Guidance System.</p>
            </section>
        </main>
    </div>
</body>
</html>