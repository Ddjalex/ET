<?php
// Test environment variables
header('Content-Type: application/json');

$botToken = getenv('BOT_TOKEN');
$strowPublic = getenv('STROW_PUBLIC_KEY');

$result = [
    'bot_token_exists' => !empty($botToken),
    'bot_token_length' => strlen($botToken),
    'strow_public_exists' => !empty($strowPublic),
    'all_env' => array_keys($_ENV)
];

echo json_encode($result, JSON_PRETTY_PRINT);
