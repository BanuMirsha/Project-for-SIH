<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/db.php';

$stmt = $pdo->prepare('SELECT username, email, role FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$role = $user['role']; // trusted: comes from DB, not from user input
$_SESSION['role'] = $role; // keep session cache in sync
$username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
$roleLabel = ucfirst($role);

$message = '';
$message_type = '';

// --- ADMIN: create a new user with an explicit role ---
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail    = trim($_POST['email'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $newRole     = strtolower(trim($_POST['role'] ?? 'private'));

    if (empty($newUsername) || empty($newEmail) || empty($newPassword)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address.';
        $message_type = 'error';
    } elseif (!in_array($newRole, ['admin', 'government', 'private'], true)) {
        $message = 'Invalid role specified.';
        $message_type = 'error';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
        $check->execute([':u' => $newUsername, ':e' => $newEmail]);

        if ($check->fetch()) {
            $message = 'A user with that username or email already exists.';
            $message_type = 'error';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (fullname, username, email, password, role) VALUES (:fullname, :username, :email, :password, :role)');
            $insert->execute([
                ':fullname' => $newUsername,
                ':username' => $newUsername,
                ':email'    => $newEmail,
                ':password' => $hashed,
                ':role'     => $newRole,
            ]);
            $message = "User '{$newUsername}' (" . ucfirst($newRole) . ') created.';
            $message_type = 'success';
        }
    }
}

// --- ADMIN: quick stats ---
$countAdmin = $countGov = $countPrivate = $countTotal = $countStandards = 0;
if ($role === 'admin') {
    $roleCounts = $pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
    $countAdmin   = (int)($roleCounts['admin'] ?? 0);
    $countGov     = (int)($roleCounts['government'] ?? 0);
    $countPrivate = (int)($roleCounts['private'] ?? 0);
    $countTotal   = array_sum($roleCounts);
    $countStandards = (int)($pdo->query("SELECT COUNT(*) AS c FROM standards")->fetch()['c'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?= htmlspecialchars($roleLabel) ?></title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="with-sidebar">
    <aside>
        <div>
            <h2>BIS Recommender</h2>
            <nav>
                <a href="dashboard.php" class="active">Overview</a>
                <a href="recommend.php">Find standards</a>
                <a href="profile.php">Profile</a>
                <?php if ($role === 'admin'): ?>
                    <a href="manage_users.php">Manage users</a>
                    <a href="import_standards.php">Import standards</a>
                <?php endif; ?>
            </nav>
        </div>
        <div>
            <a href="logout.php" class="btn-logout">Log out</a>
        </div>
    </aside>

    <main>
        <header>
            <div>
                <h1>Welcome back, <?= $username ?>! <span class="role-badge role-<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($roleLabel) ?></span></h1>
                <p class="muted">Account tier: <?= htmlspecialchars($roleLabel) ?> access</p>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <section class="grid">
            <?php if ($role === 'admin'): ?>
                <div class="card"><h3>Total users</h3><div class="value"><?= $countTotal ?></div></div>
                <div class="card"><h3>Government users</h3><div class="value"><?= $countGov ?></div></div>
                <div class="card"><h3>Private users</h3><div class="value"><?= $countPrivate ?></div></div>
                <div class="card"><h3>Standards indexed</h3><div class="value"><?= $countStandards ?></div></div>
            <?php else: ?>
                <div class="card"><h3>Access level</h3><div class="value" style="font-size:1.25rem;"><?= htmlspecialchars($roleLabel) ?></div></div>
                <div class="card"><h3>Recommendation engine</h3><div class="value" style="color:#10b981;font-size:1.25rem;">Ready</div></div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h3>AI-powered standard finder</h3>
            <p class="muted">Describe what you're building or what code you need — get ranked BIS standard matches.</p>
            <a href="recommend.php" class="btn-submit" style="display:inline-block;text-decoration:none;">Search standards &rarr;</a>
        </section>

        <?php if ($role === 'admin'): ?>
        <section class="card">
            <h3>Add new user</h3>
            <form method="POST" class="form-grid">
                <div class="form-group">
                    <label for="new_username">Username</label>
                    <input type="text" id="new_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="new_email">Email</label>
                    <input type="email" id="new_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Password</label>
                    <input type="password" id="new_password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="new_role">Role</label>
                    <select id="new_role" name="role" required>
                        <option value="private" selected>Private</option>
                        <option value="government">Government</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" style="align-self:end;">
                    <button type="submit" name="add_user" class="btn-submit">Create user</button>
                </div>
            </form>
        </section>
        <?php endif; ?>
    </main>
</body>
</html>
