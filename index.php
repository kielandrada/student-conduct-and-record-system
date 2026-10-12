<?php
session_start();
require "db_connect.php";
 
$error = "";
 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
 
    $email        = trim($_POST['email']);
    $password     = $_POST['password'];
    $selectedRole = $_POST['userType'];
 
    if (empty($email) || empty($password)) {    
        $error = "Punuan ang email at password.";
    } else {
 
        $stmt = $pdo->prepare("SELECT * FROM users WHERE gmail = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
 
        if ($user && md5($password) === $user['password']) {
 
            if ($user['role'] !== $selectedRole) {
                $error = "Hindi tugma ang napiling role sa account na ito.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['gmail']   = $user['gmail'];
                $_SESSION['role']    = $user['role'];
 
                switch ($user['role']) {
                    case 'student': header("Location: student.php"); break;
                    case 'staff':   header("Location: staff.php"); break;
                    case 'admin':   header("Location: admin.php"); break;
                }
                exit();
            }
        } else {
            $error = "Maling email o password.";
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
                  
                        <form method="post" action="" class="login-form" id="signInForm">
 

                      
                <?php if ($error): ?>
                    <p style="color:red;"><strong><?= htmlspecialchars($error) ?></strong></p>
                <?php endif; ?>
 


            <form method="post" action="" class="login-form" id="signInForm">
                <div class="role-selector" aria-label="elect account type">
                    <label class="role-option active">
                        <input type="radio" name="userType" value="student" checked>
                        <span><i class="fas fa-user-graduate"></i> Student</span>
                    </label>

                    <label class="role-option">
                        <input type="radio" name="userType" value="staff">
                        <span><i class="fas fa-user-tie"></i> Staff</span>
                    </label>

                    <label class="role-option">
                        <input type="radio" name="userType" value="admin">
                        <span><i class="fas fa-shield-halved"></i> Admin</span>
                    </label>

                </div>

                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" id="email" placeholder="Email Address" required>
                    <label for="email">Email Address</label>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>

                <div class="form-row">
                    <label class="remember-me"><input type="checkbox"> Remember Me</label>
                </div>

                <button type="submit" class="btn" id="signInButton" name="SignIn">Sign In as Student</button>
            </form>
        </div>
    </div>

    

    <script src="script.js"></script>
</body>
</html>