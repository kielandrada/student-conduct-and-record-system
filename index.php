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

// Isang lugar lang para sa mga role. 'value' => [label, icon, dashboard page]
$roles = [
    'student' => ['Student', 'fa-user-graduate', 'student.php'],
    'discipline_committee' => ['Discipline Committee', 'fa-user-tie', 'staff.php'],
    'guidance_counselor' => ['Guidance Councilor', 'fa-user-tie', 'staff.php'],
    'school_head' => ['School Head', 'fa-user-shield', 'admin.php'],
];
$databaseRoles = [
    'student' => ['student'],
    'discipline_committee' => ['discipline_committee', 'staff', 'teacher'],
    'guidance_counselor' => ['guidance_counselor', 'guidance_councilor', 'staff', 'teacher'],
    'school_head' => ['school_head', 'admin'],
];

// Kung naka-login na, huwag nang ipakita ulit ang login form
if (isset($_SESSION['role'])) {
    $sessionRole = $_SESSION['role'];
    if (in_array($sessionRole, ['staff', 'teacher', 'discipline_committee', 'guidance_counselor', 'guidance_councilor'], true)) {
        if (in_array($sessionRole, ['discipline_committee', 'guidance_counselor', 'guidance_councilor'], true)) {
            $_SESSION['staff_category'] = $sessionRole === 'guidance_councilor'
                ? 'guidance_counselor'
                : $sessionRole;
        }
        header('Location: staff.php');
        exit();
    } elseif ($sessionRole === 'admin') {
        $sessionRole = 'school_head';
    }

    if (isset($roles[$sessionRole])) {
        header('Location: ' . $roles[$sessionRole][2]);
        exit();
    }
}

$error        = '';
$email        = '';
$selectedAccountType = 'student';
$selectedStaffCategory = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $selectedAccountType = $_POST['userType'] ?? 'student';
    $selectedStaffCategory = $_POST['staffCategory'] ?? '';
    $selectedRole = $selectedAccountType === 'staff'
        ? $selectedStaffCategory
        : $selectedAccountType;

    if ($email === '' || $password === '') {
        $error = 'Please enter both the email and password.';
    } elseif (!in_array($selectedAccountType, ['student', 'staff'], true)
              || !is_string($selectedRole)
              || !isset($roles[$selectedRole])) {
        $error = 'Please select a valid role.';
        $selectedAccountType = 'student';
    } elseif ($selectedAccountType === 'staff' && !in_array($selectedStaffCategory, ['discipline_committee', 'guidance_counselor', 'school_head'], true)) {
        $error = 'Please select a valid staff category.';
    } else {
        $stmt = $pdo->prepare('SELECT id, gmail, password, role FROM users WHERE gmail = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();   // false if no match is found

        if (!$user || !hash_equals($user['password'], md5($password))) {
            // Single message for either invalid email or invalid password
            // to avoid revealing whether an email exists.
            $error = 'Incorrect email or password.';
        } elseif (!in_array($user['role'], $databaseRoles[$selectedRole], true)) {
            $error = 'The selected role does not match this account.';
        } else {
            session_regenerate_id(true);   // bagong session ID para sa seguridad
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['gmail']   = $user['gmail'];
            $_SESSION['role']    = in_array($selectedRole, ['discipline_committee', 'guidance_counselor'], true)
                ? 'staff'
                : $selectedRole;
            if (in_array($selectedRole, ['discipline_committee', 'guidance_counselor'], true)) {
                $_SESSION['staff_category'] = $selectedRole;
            } else {
                unset($_SESSION['staff_category']);
            }

            header('Location: ' . $roles[$selectedRole][2]);
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
                    <?php foreach (['student' => ['Student', 'fa-user-graduate'], 'staff' => ['Staff', 'fa-user-tie']] as $value => [$label, $icon]): ?>
                        <label class="role-option">
                            <input type="radio" name="userType" value="<?= $value ?>"
                                <?= $value === $selectedAccountType ? 'checked' : '' ?>>
                            <span><i class="fas <?= $icon ?>"></i> <?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="staff-category-field" id="staffCategoryField"
                     <?= $selectedAccountType === 'staff' ? '' : 'hidden' ?>>
                    <label for="staffCategory">Staff category</label>
                    <select name="staffCategory" id="staffCategory">
                        <option value="">Select your staff category</option>
                        <?php foreach ([
                            'discipline_committee' => 'Discipline Committee',
                            'guidance_counselor' => 'Guidance Councilor',
                            'school_head' => 'School Head',
                        ] as $value => $label): ?>
                            <option value="<?= $value ?>"
                                <?= $selectedStaffCategory === $value ? 'selected' : '' ?>>
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

    <!-- Dati: src="script.js" (mali ang path, nasa js/ folder ang file) -->
    <script src="js/script.js"></script>
</body>
</html>