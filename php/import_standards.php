<?php
// import_standards.php
// Admin-only one-off/re-run utility: loads data/standards_verified.csv
// into the `standards` table. Safe to re-run — it upserts on is_code.
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';
$imported = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_import'])) {
    $csvPath = __DIR__ . '/../data/standards_verified.csv';

    if (!file_exists($csvPath)) {
        $message = 'CSV file not found at ' . htmlspecialchars($csvPath);
        $message_type = 'error';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO standards
                (is_code, title, scope_text, sector, status, current_edition_detail, superseded_by_or_notes, verification_source)
             VALUES (:is_code, :title, :scope_text, :sector, :status, :edition, :notes, :source)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                scope_text = VALUES(scope_text),
                sector = VALUES(sector),
                status = VALUES(status),
                current_edition_detail = VALUES(current_edition_detail),
                superseded_by_or_notes = VALUES(superseded_by_or_notes),
                verification_source = VALUES(verification_source)"
        );

        if (($handle = fopen($csvPath, 'r')) !== false) {
            $headers = fgetcsv($handle); // skip header row
            $pdo->beginTransaction();

            while (($row = fgetcsv($handle)) !== false) {
                if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                    continue;
                }
                $status = trim($row[4] ?? 'Active');
                if (!in_array($status, ['Active', 'Withdrawn', 'Reaffirmed', 'Draft'], true)) {
                    $status = 'Active';
                }
                $stmt->execute([
                    ':is_code' => trim($row[0] ?? ''),
                    ':title'   => trim($row[1] ?? ''),
                    ':scope_text' => trim($row[2] ?? ''),
                    ':sector'  => trim($row[3] ?? ''),
                    ':status'  => $status,
                    ':edition' => trim($row[5] ?? ''),
                    ':notes'   => trim($row[6] ?? ''),
                    ':source'  => trim($row[7] ?? ''),
                ]);
                $imported++;
            }

            $pdo->commit();
            fclose($handle);
            $message = "Imported/updated {$imported} standards.";
            $message_type = 'success';
        } else {
            $message = 'Could not open the CSV file.';
            $message_type = 'error';
        }
    }
}

$countStmt = $pdo->query("SELECT COUNT(*) AS c FROM standards");
$totalStandards = $countStmt->fetch()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Import Standards</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="page-narrow">
    <div class="card">
        <h2>Import BIS standards</h2>
        <p class="muted">Loads <code>data/standards_verified.csv</code> into the <code>standards</code> table. Safe to re-run.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <p>Currently in database: <strong><?= (int)$totalStandards ?></strong> standards.</p>

        <form method="POST">
            <button type="submit" name="run_import" class="btn-submit">Run import</button>
        </form>

        <p style="margin-top:16px;"><a href="dashboard.php">&larr; Back to dashboard</a></p>
    </div>
</body>
</html>
