<?php
// register.php — public self-registration.
// Deliberately only offers 'government' / 'private' roles.
// Admin accounts are never created from a public form — see README.
session_start();
require_once 'db.php';

$message = '';
$message_type = '';
$allowed_roles = ['government' => 'Government Sector', 'private' => 'Private Sector'];
$clearStorage = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($fullname) || empty($email) || empty($username) || empty($role) || empty($password)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'warning';
    } elseif (!array_key_exists($role, $allowed_roles)) {
        $message = 'Please select a valid sector.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'warning';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $message_type = 'danger';
    } else {
        try {
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
            $checkStmt->execute([$email, $username]);

            if ($checkStmt->fetch()) {
                $message = 'Username or email is already registered.';
                $message_type = 'warning';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare(
                    'INSERT INTO users (fullname, email, username, role, password) VALUES (?, ?, ?, ?, ?)'
                );
                $insertStmt->execute([$fullname, $email, $username, $role, $hashed]);

                $message = 'Account created successfully! You can now log in.';
                $message_type = 'success';
                $clearStorage = true;
                $_POST = [];
            }
        } catch (PDOException $e) {
            $message = 'Something went wrong. Please try again later.';
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create an account — BIS Standards Recommender</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="page-center">
    <div class="auth-card">
        <h1>Create account</h1>
        <p class="muted">Join to search and get AI-recommended BIS standards</p>

        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" id="userRegisterForm" autocomplete="off">
            <label for="fullname">Full name</label>
            <input type="text" id="fullname" name="fullname" placeholder="Jane Doe"
                   value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>

            <label for="email">Email address</label>
            <input type="email" id="email" name="email" placeholder="name@example.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="janedoe"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

            <label for="role">Sector</label>
            <select id="role" name="role" required>
                <option value="" disabled <?= empty($_POST['role']) ? 'selected' : '' ?>>Select your sector</option>
                <?php foreach ($allowed_roles as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (($_POST['role'] ?? '') === $value) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="At least 6 characters" required>

            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>

            <button type="submit" class="btn-submit">Register</button>
        </form>

        <p class="footer-text">Already have an account? <a href="index.php">Sign in</a></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                const fields = ['fullname', 'email', 'username'];
                const clear = <?= $clearStorage ? 'true' : 'false' ?>;

                fields.forEach(function (id) {
                    const el = document.getElementById(id);
                    const key = 'bis_reg_' + id;

                    if (clear) {
                        localStorage.removeItem(key);
                        return;
                    }
                    const saved = localStorage.getItem(key);
                    if (!el.value && saved) el.value = saved;

                    el.addEventListener('input', function () {
                        localStorage.setItem(key, el.value);
                    });
                });
                // Never store password fields — intentionally not wired up above.
            } catch (e) {
                // localStorage unavailable — fail silently
            }
        });
    </script>
</body>
</html>
