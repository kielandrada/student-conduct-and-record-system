<?php
/**
 * index.php  —  LOGIN PAGE
 *
 * Daloy:
 *  1. Kunin ang email, password, at role mula sa form
 *  2. Hanapin ang user sa database
 *  3. I-check kung tama ang password AT tugma ang piniling role
 *  4. Kung tama lahat, i-save sa session at ipadala sa tamang dashboard
 */

session_start();
require 'db_connect.php';

// Isang lugar lang para sa mga role. Kung may idadagdag na role,
// dito lang magdadagdag:  'value' => [label, icon, dashboard page]
$roles = [
    'student' => ['Student', 'fa-user-graduate', 'student.php'],
    'staff'   => ['Staff',   'fa-user-tie',      'staff.php'],
    'admin'   => ['Admin',   'fa-shield-halved', 'admin.php'],
];

// Kung naka-login na, huwag nang ipakita ulit ang login form
if (isset($_SESSION['role'], $roles[$_SESSION['role']])) {
    header('Location: ' . $roles[$_SESSION['role']][2]);
    exit();
}

$error        = '';
$email        = '';
$selectedRole = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $selectedRole = $_POST['userType'] ?? 'student';

    if ($email === '' || $password === '') {
        $error = 'Please enter both the email and password.';
    } elseif (!isset($roles[$selectedRole])) {
        $error = 'Please select a valid role.';
        $selectedRole = 'student';
    } else {
        $stmt = $pdo->prepare('SELECT id, gmail, password, role FROM users WHERE gmail = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();   // false if no match is found

        if (!$user || !hash_equals($user['password'], md5($password))) {
            // Single message for either invalid email or invalid password
            // to avoid revealing whether an email exists.
            $error = 'Incorrect email or password.';
        } elseif ($user['role'] !== $selectedRole) {
            $error = 'The selected role does not match this account.';
        } else {
            session_regenerate_id(true);   // bagong session ID para sa seguridad
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['gmail']   = $user['gmail'];
            $_SESSION['role']    = $user['role'];

            header('Location: ' . $roles[$user['role']][2]);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Conduct and Guidance Record</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
</head>
<body>
    <div class="login-page">
        <div class="login-card">

            <div class="brand-wrap">
                <div class="brand-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <h1 class="form-title">Student Portal</h1>
                    <p class="subtitle">Sign in to continue</p>
                </div>
            </div>

            <!-- ISANG form lang ito. (Dati dalawa ang form tag, mali iyon.) -->
            <form method="post" action="" class="login-form" id="signInForm">

                <?php if ($error): ?>
                    <p style="color:red;"><strong><?= htmlspecialchars($error) ?></strong></p>
                <?php endif; ?>

                <div class="role-selector" aria-label="Select account type">
                    <?php foreach ($roles as $value => [$label, $icon]): ?>
                        <label class="role-option">
                            <input type="radio" name="userType" value="<?= $value ?>"
                                <?= $value === $selectedRole ? 'checked' : '' ?>>
                            <span><i class="fas <?= $icon ?>"></i> <?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" id="email" placeholder="Email Address"
                           value="<?= htmlspecialchars($email) ?>" required>
                    <label for="email">Email Address</label>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>

                <button type="submit" class="btn" id="signInButton" name="SignIn">Sign In as Student</button>
            </form>

        </div>
    </div>

    <!-- Dati: src="script.js" (mali ang path, nasa js/ folder ang file) -->
    <script src="js/script.js"></script>
</body>
</html>