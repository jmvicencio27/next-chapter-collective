<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $code, bool $success, string $message): void {
    http_response_code($code);
    $wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $title = $success ? 'Thank you' : 'Enquiry update';
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . ' | Next Chapter Collective</title><link rel="stylesheet" href="assets/site.css"><main class="container privacy-main"><p class="eyebrow">NEXT CHAPTER COLLECTIVE</p><h1>' . $title . '</h1><p>' . $safeMessage . '</p><a class="button button-dark" href="contact.html#connect">Return to the website</a></main></html>';
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Please use the enquiry form to start a conversation.');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16000) {
    respond(413, false, 'Your enquiry is too long. Please shorten it and try again.');
}
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    respond(403, false, 'Please send your enquiry from our website.');
}
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
    $host = parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!$origin || !$host || strcasecmp($origin, $host) !== 0) {
        respond(403, false, 'Please send your enquiry from our website.');
    }
}
$fields = [];
foreach (['name', 'email', 'interest', 'program', 'message', 'website', 'consent', 'company', 'role', 'mentor'] as $key) {
    if (isset($_POST[$key]) && !is_string($_POST[$key])) {
        respond(422, false, 'Please check your details and try again.');
    }
    $fields[$key] = trim($_POST[$key] ?? '');
}
if ($fields['website'] !== '') {
    respond(422, false, 'Please check your details and try again.');
}
if (strlen($fields['name']) < 1 || strlen($fields['name']) > 400 || preg_match('/[\r\n]/', $fields['name'])) {
    respond(422, false, 'Please enter your name.');
}
if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL) || strlen($fields['email']) > 254 || preg_match('/[\r\n]/', $fields['email'])) {
    respond(422, false, 'Please enter a valid email address.');
}
if (!in_array($fields['interest'], ['business', 'career', 'both', 'seminars', 'training', 'networking'], true)) {
    respond(422, false, 'Please choose the mentoring path that interests you.');
}
if (!in_array($fields['program'], ['Not sure yet', 'Clarity Session', 'Business Growth Sprint', 'Career Next Step', 'Business Seminar / Workshop', 'Corporate Training', 'Technology & AI Workshop', 'Networking & Peer Circle'], true)) {
    respond(422, false, 'Please choose a program or select Help me choose.');
}
if (strlen($fields['message']) < 20 || strlen($fields['message']) > 12000 || $fields['consent'] !== 'yes') {
    respond(422, false, 'Please add at least 20 characters about your goals and agree to the privacy notice.');
}
if (strlen($fields['company']) > 600 || strlen($fields['role']) > 600) {
    respond(422, false, 'Please shorten your company or role details.');
}
if (!in_array($fields['mentor'], ['Help me choose', 'Abi', 'RJ', 'Lyka', 'Joma'], true)) {
    respond(422, false, 'Please choose a mentor or select Help me choose.');
}
$settings = require __DIR__ . '/settings.php';
$recipient = $settings['recipient_email'] ?? '';
$sender = $settings['sender_email'] ?? '';
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($sender, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient . $sender)) {
    respond(503, false, 'Online enquiries are temporarily unavailable. Please try again later.');
}

// Shared-IP limits discourage repeated submissions without retaining the visitor's raw IP.
$key = hash('sha256', __DIR__ . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$rateFile = sys_get_temp_dir() . '/ncc-enquiry-' . $key . '.json';
$handle = @fopen($rateFile, 'c+');
if ($handle === false || !flock($handle, LOCK_EX)) {
    if (is_resource($handle)) fclose($handle);
    respond(503, false, 'Online enquiries are temporarily unavailable. Please try again later.');
}
$state = json_decode(stream_get_contents($handle) ?: '{}', true);
$now = time();
if (!is_array($state) || $now - (int)($state['start'] ?? 0) > 600) {
    $state = ['start' => $now, 'count' => 0];
}
if ((int)($state['count'] ?? 0) >= 5) {
    flock($handle, LOCK_UN);
    fclose($handle);
    header('Retry-After: 600');
    respond(429, false, 'You have sent several enquiries recently. Please wait ten minutes before trying again.');
}
$state['count'] = (int)($state['count'] ?? 0) + 1;
rewind($handle);
ftruncate($handle, 0);
fwrite($handle, json_encode($state));
flock($handle, LOCK_UN);
fclose($handle);
@chmod($rateFile, 0600);

$body = "New mentoring enquiry\n\nName: " . $fields['name'] . "\nEmail: " . $fields['email'] . "\nCompany: " . $fields['company'] . "\nRole: " . $fields['role'] . "\nPreferred mentor: " . $fields['mentor'] . "\nInterest: " . $fields['interest'] . "\nProgram: " . $fields['program'] . "\n\nChallenge / goal:\n" . $fields['message'] . "\n\nPrivacy consent: yes\n";
$headers = [
    'From: Next Chapter Collective <' . $sender . '>',
    'Reply-To: ' . $fields['email'],
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
];
$sent = function_exists('mail') && @mail($recipient, 'Next Chapter Collective | Mentoring enquiry', $body, implode("\r\n", $headers));
if (!$sent) {
    respond(503, false, 'Your enquiry could not be sent right now. Please try again later.');
}
respond(200, true, 'Thank you. Your enquiry has been submitted. We look forward to discussing your next chapter.');
