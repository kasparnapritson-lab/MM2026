<?php
/**
 * MM2026 E-posti teavituste saatja
 * Tooma Tööriist AS
 * 
 * Kasutus: POST /send_email.php
 * Parameetrid: type, subject, message, secret
 */

// ── SEADED ──────────────────────────────────────────────
define('SECRET_KEY', 'Tooma2026mäng');  // Muuda see unikaalseks!
define('FROM_EMAIL', 'mm2026@toomatool.ee');
define('FROM_NAME',  'MM2026 Ennustusmäng');
define('ALLOWED_ORIGIN', 'https://mm2026.toomatool.ee');
// ────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ainult POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Loe POST andmed
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Kontrolli secret key
if (empty($input['secret']) || $input['secret'] !== SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// Kontrolli kohustuslikud väljad
$type    = $input['type']    ?? '';
$subject = $input['subject'] ?? '';
$message = $input['message'] ?? '';
$recipients = $input['recipients'] ?? []; // Array e-posti aadressidest

if (!$subject || !$message || empty($recipients)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// Filtreeri ja valideeeri e-posti aadressid
$valid_recipients = array_filter($recipients, function($email) {
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
});

if (empty($valid_recipients)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No valid recipients']);
    exit;
}

// ── E-KIRJA SISU ────────────────────────────────────────
$html_body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)">
  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#1a1a2e,#c0392b);padding:28px 32px;text-align:center">
      <div style="font-size:32px;margin-bottom:6px">⚽</div>
      <div style="font-family:Arial Black,sans-serif;font-size:26px;color:#fff;letter-spacing:2px">MM2026</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.6);margin-top:4px;letter-spacing:1px">ENNUSTUSVÕISTLUS</div>
    </td>
  </tr>
  <!-- Body -->
  <tr>
    <td style="padding:32px">
      <p style="font-size:16px;color:#1a1a2e;line-height:1.6;margin:0 0 20px">' . nl2br(htmlspecialchars($message)) . '</p>
      <div style="text-align:center;margin:28px 0">
        <a href="https://mm2026.toomatool.ee/login.html" 
           style="background:#c0392b;color:#fff;padding:13px 32px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;display:inline-block">
          Mine mängu →
        </a>
      </div>
    </td>
  </tr>
  <!-- Footer -->
  <tr>
    <td style="background:#f8f8f8;padding:18px 32px;text-align:center;border-top:1px solid #eee">
      <p style="font-size:12px;color:#999;margin:0">
        MM2026 ennustusmäng · Tooma Tööriist AS<br>
        <a href="https://mm2026.toomatool.ee/privacy.html" style="color:#c0392b">Privaatsuspoliitika</a> · 
        Küsimused: <a href="mailto:info@toomatool.ee" style="color:#c0392b">info@toomatool.ee</a>
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>';

// ── SAADA KIRJAD ────────────────────────────────────────
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
$headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$sent  = 0;
$failed = 0;
$errors = [];

foreach ($valid_recipients as $email) {
    $email = trim($email);
    $result = mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html_body, $headers);
    if ($result) {
        $sent++;
    } else {
        $failed++;
        $errors[] = $email;
    }
}

// Logi teavitus (optional)
$log_line = date('Y-m-d H:i:s') . " | type=$type | sent=$sent | failed=$failed\n";
file_put_contents(__DIR__ . '/email_log.txt', $log_line, FILE_APPEND | LOCK_EX);

echo json_encode([
    'ok'     => $sent > 0,
    'sent'   => $sent,
    'failed' => $failed,
    'errors' => $errors
]);
