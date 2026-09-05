<?php
// manage_users.php — admin only.
session_start();
require_once 'db.php';

// FIX: this check was commented out in the original file, which let
// anyone reach this page and reset passwords / change roles. It must
// run before anything else on the page.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$userId) {
            $error = 'Invalid user ID.';
        } elseif ($action === 'update_details') {
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $role     = strtolower(trim($_POST['role'] ?? 'private'));
            $allowedRoles = ['admin', 'government', 'private'];

            if (empty($username) || empty($email)) {
                $error = 'Username and email cannot be empty.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format.';
            } elseif (!in_array($role, $allowedRoles, true)) {
                $error = 'Invalid role selected.';
            } elseif ($userId === (int)$_SESSION['user_id'] && $role !== 'admin') {
                // Prevent an admin from locking themselves out by accident.
                $error = "You can't remove your own admin role.";
            } else {
                try {
                    $stmt = $pdo->prepare('UPDATE users SET username = :username, email = :email, role = :role WHERE id = :id');
                    $stmt->execute([':username' => $username, ':email' => $email, ':role' => $role, ':id' => $userId]);
                    $message = 'User details updated successfully!';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
                }
            }
        } elseif ($action === 'reset_password') {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen($newPassword) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $stmt->execute([':password' => $hashed, ':id' => $userId]);
                $message = 'Password reset successfully!';
            }
        }
    }
}

$users = $pdo->query('SELECT id, username, email, role, created_at FROM users ORDER BY id DESC')->fetchAll();

$editUser = null;
if (isset($_GET['edit_id'])) {
    $editId = filter_var($_GET['edit_id'], FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $editUser = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage users</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="page-wide">
<div class="container">
    <div class="page-header">
        <h2>User management</h2>
        <a href="dashboard.php" class="btn-outline">&larr; Back to dashboard</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($editUser): ?>
    <div class="card">
        <h3>Editing: <?= htmlspecialchars($editUser['username']) ?> (ID <?= (int)$editUser['id'] ?>)</h3>
        <div class="form-grid">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="update_details">
                <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
                <h4>Update profile</h4>
                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($editUser['username']) ?>" required>
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($editUser['email']) ?>" required>
                <label>Role</label>
                <select name="role">
                    <?php foreach (['admin', 'government', 'private'] as $r): ?>
                        <option value="<?= $r ?>" <?= $editUser['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-submit">Save changes</button>
                <a href="manage_users.php" style="margin-left:10px;">Cancel</a>
            </form>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
                <h4>Reset password</h4>
                <label>New password</label>
                <input type="password" name="new_password" minlength="8" required>
                <label>Confirm new password</label>
                <input type="password" name="confirm_password" minlength="8" required>
                <button type="submit" class="btn-submit btn-secondary">Reset password</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3>All users (<?= count($users) ?>)</h3>
        <table>
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($u['role']) ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td><?= htmlspecialchars($u['created_at'] ?? 'N/A') ?></td>
                    <td><a href="manage_users.php?edit_id=<?= (int)$u['id'] ?>">Edit / reset password</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
