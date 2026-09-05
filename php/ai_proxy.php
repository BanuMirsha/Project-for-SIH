<?php
// ai_proxy.php
// Keeps the AI microservice's address and any future auth/API key
// server-side. Also logs the query for the feedback-loop table.
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
$query = trim($payload['query'] ?? '');

if ($query === '') {
    echo json_encode(['results' => []]);
    exit;
}

$ch = curl_init(AI_SERVICE_URL . '/recommend');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $rawInput,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'AI service unavailable', 'results' => []]);
    exit;
}

$decoded = json_decode($response, true);
$topResult = $decoded['results'][0]['is_code'] ?? null;

try {
    $log = $pdo->prepare('INSERT INTO search_logs (user_id, query_text, top_result) VALUES (:uid, :q, :top)');
    $log->execute([':uid' => $_SESSION['user_id'], ':q' => $query, ':top' => $topResult]);
} catch (PDOException $e) {
    // Logging is best-effort — never block the actual search on it.
}

echo $response;
