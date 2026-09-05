<?php
// profile.php
// Users can change their own username/email/password.
// Role is intentionally NEVER accepted from this form — it is
// read-only here and can only be changed by an admin via
// manage_users.php. This is the fix for the self-escalation bug
// where a plain user could POST role=admin and grant themselves access.
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password      = $_POST['new_password'] ?? '';
    $confirm_password  = $_POST['confirm_password'] ?? '';
    // Note: no $_POST['role'] is ever read here.

    if (empty($username) || empty($email)) {
        $error = 'Username and email cannot be empty.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE (email = :email OR username = :username) AND id != :id LIMIT 1');
            $checkStmt->execute(['email' => $email, 'username' => $username, 'id' => $user_id]);

            if ($checkStmt->fetch()) {
                $error = 'Username or email is already in use by another account.';
            } else {
                $pwdStmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
                $pwdStmt->execute(['id' => $user_id]);
                $currentUser = $pwdStmt->fetch();

                $updatePassword = false;
                $newHash = null;

                if (!empty($new_password) || !empty($current_password) || !empty($confirm_password)) {
                    if (empty($current_password)) {
                        $error = 'Enter your current password to set a new one.';
                    } elseif (!password_verify($current_password, $currentUser['password'] ?? '')) {
                        $error = 'Current password does not match our records.';
                    } elseif (strlen($new_password) < 8) {
                        $error = 'New password must be at least 8 characters.';
                    } elseif ($new_password !== $confirm_password) {
                        $error = 'New password and confirmation do not match.';
                    } else {
                        $updatePassword = true;
                        $newHash = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                }

                if (empty($error)) {
                    if ($updatePassword) {
                        $upd = $pdo->prepare('UPDATE users SET username = :username, email = :email, password = :password WHERE id = :id');
                        $upd->execute(['username' => $username, 'email' => $email, 'password' => $newHash, 'id' => $user_id]);
                    } else {
                        $upd = $pdo->prepare('UPDATE users SET username = :username, email = :email WHERE id = :id');
                        $upd->execute(['username' => $username, 'email' => $email, 'id' => $user_id]);
                    }
                    $_SESSION['username'] = $username;
                    $message = 'Profile updated successfully!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

$stmt = $pdo->prepare('SELECT username, email, role, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My profile</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="page-narrow">
    <div class="card">
        <h2>My profile</h2>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

            <label>Role</label>
            <input type="text" value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" disabled>
            <p class="muted" style="margin-top:-8px;font-size:12px;">Role changes must be made by an admin, from Manage Users.</p>

            <hr style="margin:20px 0;">
            <h4>Change password</h4>
            <p class="muted">Leave blank to keep your current password.</p>

            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password">

            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8">

            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">

            <p class="muted">Member since <?= !empty($user['created_at']) ? htmlspecialchars(date('F j, Y', strtotime($user['created_at']))) : 'N/A' ?></p>

            <button type="submit" class="btn-submit">Save changes</button>
        </form>

        <p style="margin-top:16px;"><a href="dashboard.php">&larr; Back to dashboard</a> &middot; <a href="logout.php">Log out</a></p>
    </div>
</body>
</html>
