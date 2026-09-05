<?php
// admin_register.php
// FIX: the original version of this page had no auth check at all —
// anyone could open it and create their own admin account. It now
// only works for someone who is already logged in as an admin.
// For everyday use, prefer "Add new user" on the dashboard (role
// dropdown includes Admin) — this page is kept as a dedicated form.
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute(['username' => $username, 'email' => $email]);

        if ($stmt->fetch()) {
            $errors[] = 'Username or email already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (fullname, username, email, password, role) VALUES (:fullname, :username, :email, :password, :role)');
            $insert->execute([
                'fullname' => $username,
                'username' => $username,
                'email'    => $email,
                'password' => $hashed,
                'role'     => 'admin',
            ]);
            $success = 'Admin account created successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create admin account</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="page-narrow">
    <div class="card">
        <h2>Create another admin account</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left:18px;">
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" minlength="8" required>
            <label>Confirm password</label>
            <input type="password" name="confirm_password" minlength="8" required>
            <button type="submit" class="btn-submit">Create admin</button>
        </form>

        <p style="margin-top:16px;"><a href="dashboard.php">&larr; Back to dashboard</a></p>
    </div>
</body>
</html>
