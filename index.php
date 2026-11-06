<?php
/**
 * index.php  —  LOGIN PAGE
 *
 * Daloy:
 *  1. Kunin ang email, password, account type (Student/Staff), at staff category
 *  2. Hanapin ang user sa database
 *  3. I-check ang password, account type, at staff category
 *  4. Kung tama lahat, i-save sa session at ipadala sa tamang dashboard
 */

require 'auth.php';
require 'db_connect.php';

// Kung naka-login na, huwag nang ipakita ulit ang login form
if ($home = currentDashboard()) {
    header('Location: ' . $home);
    exit();
}

// Mga pwedeng piliin sa unang hanay: value => [label, icon]
$accountTypes = [
    'student' => ['Student', 'fa-user-graduate'],
    'staff'   => ['Staff',   'fa-user-tie'],
];

$error        = '';
$email        = '';
$accountType  = 'student';
$category     = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email       = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $password    = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $accountType = $_POST['userType'] ?? 'student';
    $category    = $_POST['staffCategory'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both the email and password.';
    } elseif (!is_string($accountType) || !isset($accountTypes[$accountType])) {
        $error = 'Please select a valid account type.';
        $accountType = 'student';
    } elseif ($accountType === 'staff'
              && (!is_string($category) || !isset(STAFF_CATEGORIES[$category]))) {
        $error = 'Please select your staff category.';
    } else {
        $stmt = $pdo->prepare('SELECT id, gmail, password, role, staff_category FROM users WHERE gmail = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();   // false kung walang nahanap

        if (!$user || !verifyUserPassword($user['password'], $password)) {
            // Iisang mensahe para sa maling email O maling password,
            // para hindi malaman ng iba kung existing ang email.
            $error = 'Incorrect email or password.';
        } elseif ($accountType === 'student' && $user['role'] !== 'student') {
            $error = 'This account is not a student account. Please choose the correct account type.';
        } elseif ($accountType === 'staff'
                  && (!isset(STAFF_CATEGORIES[$category])
                      || $user['staff_category'] !== $category
                      || ($category === 'school_head' && !in_array($user['role'], ['staff', 'admin'], true))
                      || ($category !== 'school_head' && $user['role'] !== 'staff'))) {
            $error = 'The selected staff category does not match this account.';
        } else {
            loginUser($user);
            header('Location: ' . dashboardFor($user['role'], $user['staff_category']));
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
        <div class="login-content">
            <h1 class="system-title">Student Conduct and Violation Record Management System</h1>

            <div class="login-card">
                <div class="brand-wrap">
                    <div class="brand-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <h2 class="form-title">Student Portal</h2>
                        <p class="subtitle">Sign in to continue</p>
                    </div>
                </div>

                <form method="post" action="" class="login-form" id="signInForm">

                    <?php if ($error): ?>
                        <p style="color:red;"><strong><?= htmlspecialchars($error) ?></strong></p>
                    <?php endif; ?>

                    <div class="role-selector" aria-label="Select account type">
                        <?php foreach ($accountTypes as $value => [$label, $icon]): ?>
                            <label class="role-option">
                                <input type="radio" name="userType" value="<?= $value ?>"
                                    <?= $value === $accountType ? 'checked' : '' ?>>
                                <span><i class="fas <?= $icon ?>"></i> <?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="staff-category-field" id="staffCategoryField"
                         <?= $accountType === 'staff' ? '' : 'hidden' ?>>
                        <label for="staffCategory">Staff category</label>
                        <select name="staffCategory" id="staffCategory">
                            <option value="">Select your staff category</option>
                            <?php foreach (STAFF_CATEGORIES as $value => [$label]): ?>
                                <option value="<?= $value ?>" <?= $category === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
    </div>

    <script src="js/script.js"></script>
</body>
</html>