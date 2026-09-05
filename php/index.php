<?php
// index.php — login
session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare('SELECT id, username, email, password, role FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // prevent session fixation
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid email or password.';
    } else {
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — BIS Standards Recommender</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="page-center">
    <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="muted">Sign in to search and get AI-recommended BIS standards</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="name@example.com" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="********" required>

            <button type="submit" class="btn-submit">Sign in</button>
        </form>

        <p class="footer-text">Don't have an account? <a href="register.php">Create one</a></p>
    </div>

    <script>
        // Remember the email only (never the password) so returning
        // users don't have to retype it. Cleared automatically if the
        // browser's storage is unavailable — wrapped defensively.
        document.addEventListener('DOMContentLoaded', function () {
            try {
                const emailInput = document.getElementById('email');
                const saved = localStorage.getItem('bis_signin_email');
                if (!emailInput.value && saved) emailInput.value = saved;

                emailInput.addEventListener('input', function () {
                    localStorage.setItem('bis_signin_email', emailInput.value);
                });
            } catch (e) {
                // localStorage unavailable (private browsing etc.) — fail silently
            }
        });
    </script>
</body>
</html>
