<?php
$logFile = '/var/www/blogme/webhook.log';
$secret = 'Slk20010521@Digitalocean'; // Your webhook secret

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

// Log the incoming request time
file_put_contents($logFile, "Webhook received at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Log the raw payload for reference
file_put_contents($logFile, "Payload:\n" . $payload . "\n", FILE_APPEND);

$computedHash = 'sha256=' . hash_hmac('sha256', $payload, $secret);

// Log the signature comparison
file_put_contents($logFile, "Signature header: $signature\nComputed hash: $computedHash\n", FILE_APPEND);

if (!hash_equals($computedHash, $signature)) {
    http_response_code(403);
    file_put_contents($logFile, "Invalid signature. Aborting.\n\n", FILE_APPEND);
    exit('Invalid signature.');
}

$data = json_decode($payload, true);

// Log the ref (branch)
file_put_contents($logFile, "Git ref: " . $data['ref'] . "\n", FILE_APPEND);

if ($data['ref'] === 'refs/heads/production') {
    $output = shell_exec('cd /var/www/blogme/BlogMe && git pull origin production 2>&1');
    file_put_contents($logFile, "Deployment output:\n$output\n\n", FILE_APPEND);
}

