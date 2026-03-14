
<?php

session_start();

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';

// Store form values for repopulating on error
$registerName = $_SESSION['register_name'] ?? '';
$registerEmail = $_SESSION['register_email'] ?? '';

// Only clear the temporary error/form keys, not the entire session
unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['active_form'], $_SESSION['register_name'], $_SESSION['register_email']);

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Report Management System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
    <body class="login-page">


    <div class="login-container">
        <div class="branding-side">
            <div class="branding-content">
                <div class="logo-section">
                    <i class="fa-solid fa-file-circle-check"></i>
                    <h1>Report Management System</h1>
                </div>
                <p class="tagline">Streamline your reporting process with ease and efficiency</p>
                <div class="features">
                    <div class="feature-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Submit reports instantly</span>
                    </div>
                    <div class="feature-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Track status in real-time</span>
                    </div>
                    <div class="feature-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Get admin responses quickly</span>
                    </div>
                    <div class="feature-item">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>Secure and reliable</span>
                    </div>
                </div>
                <div class="illustration">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
        </div>

        <div class="forms-side">
            <div class="form-wrapper">
                <!-- LOGIN FORM -->
                <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
                    <form action="login_register.php" method="post">
                        <h2>Welcome Back</h2>
                        <p class="form-subtitle">Login to your account</p>
                        
                        <?= showError($errors['login']); ?>
                        
                        <div class="input-group">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" placeholder="Email Address" required>
                        </div>
                        
                        <div class="input-group">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        
                        <button type="submit" name="login" class="submit-btn">
                            <span>Login</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                        
                        <div class="form-switch">
                            <p>Don't have an account? <a href="#" onclick="showForm('register-form'); return false;">Register here</a></p>
                        </div>
                    </form>
                </div>

                <!-- REGISTER FORM -->
                <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
                    <form action="login_register.php" method="post">
                        <h2>Create Account</h2>
                        <p class="form-subtitle">Join our platform today</p>
                        
                        <?= showError($errors['register']); ?>
                        
                        <div class="input-group">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="name" placeholder="Full Name" value="<?= htmlspecialchars($registerName) ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($registerEmail) ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        
                        <div class="input-group">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                        </div>
                        
                        <button type="submit" name="register" class="submit-btn">
                            <span>Register</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                        
                        <div class="form-switch">
                            <p>Already have an account? <a href="#" onclick="showForm('login-form'); return false;">Login here</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
