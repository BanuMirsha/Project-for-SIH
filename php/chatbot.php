<?php
// chatbot.php — small conversational widget, embedded via iframe on
// the dashboard. Talks to the same Flask service's /chat route,
// which returns a formatted text reply instead of structured JSON.
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['reply' => 'Please log in first.']);
        exit;
    }
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');

    $ch = curl_init(AI_SERVICE_URL . '/chat');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $rawInput,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        echo json_encode(['reply' => 'Error: unable to reach the AI service.']);
        exit;
    }
    echo $response;
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Please log in first.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Standards chat</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<div class="chat-card">
    <div class="chat-header">Standards assistant</div>
    <div class="messages" id="chatBox">
        <div class="msg bot">Ask me about any BIS standard — e.g. "wind load design".</div>
    </div>
    <form class="input-row" id="chatForm">
        <input type="text" id="userInput" placeholder="Type a message..." autocomplete="off" required>
        <button type="submit">Send</button>
    </form>
</div>
<script>
const form = document.getElementById('chatForm');
const input = document.getElementById('userInput');
const chatBox = document.getElementById('chatBox');

function appendMessage(text, sender) {
    const div = document.createElement('div');
    div.className = 'msg ' + sender;
    div.textContent = text;
    chatBox.appendChild(div);
    chatBox.scrollTop = chatBox.scrollHeight;
}

form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    appendMessage(text, 'user');
    input.value = '';
    try {
        const res = await fetch('chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        });
        const data = await res.json();
        appendMessage(data.reply || 'No reply received.', 'bot');
    } catch (err) {
        appendMessage('Error contacting server.', 'bot');
    }
});
</script>
</body>
</html>
