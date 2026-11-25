<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text($value, int $max = 255): string {
    $text = trim(is_scalar($value) ? (string)$value : '');
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
    return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
}

function clean_url($value): string {
    $url = clean_text($value, 800);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return '';
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

function clean_click_id($value): string {
    $id = clean_text($value, 255);
    return preg_match('/^[A-Za-z0-9._~-]{1,255}$/', $id) ? $id : '';
}

function append_jsonl(string $directory, string $file, array $record): bool {
    if (!is_dir($directory) && !@mkdir($directory, 0750, true)) return false;
    $path = rtrim($directory, '/') . '/' . basename($file);
    return @file_put_contents(
        $path,
        json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    ) !== false;
}

function cleanup_storage(string $directory, int $retentionDays): void {
    if (!is_dir($directory)) return;
    $now = time();
    foreach ((array)glob(rtrim($directory, '/') . '/rate-*.txt') as $path) {
        if (is_file($path) && $now - (int)@filemtime($path) > 7200) @unlink($path);
    }
    $cutoff = $now - max(1, $retentionDays) * 86400;
    foreach ((array)glob(rtrim($directory, '/') . '/*.jsonl') as $path) {
        if (is_file($path) && (int)@filemtime($path) < $cutoff) @unlink($path);
    }
}

function add_field(array &$fields, string $attribute, string $value): void {
    if ($attribute === '' || $value === '') return;
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,99}$/', $attribute)) return;
    $fields[] = ['Attribute' => $attribute, 'Value' => $value];
}

function allowed_value(string $value, $allowed): bool {
    if ($value === '') return true;
    if (!is_array($allowed) || !$allowed) return false;
    $values = array_map('strval', $allowed);
    return in_array($value, $values, true);
}

function origin_allowed(array $config): bool {
    $origin = clean_text($_SERVER['HTTP_ORIGIN'] ?? '', 500);
    if ($origin === '') return true;
    $allowed = is_array($config['allowed_origins'] ?? null) ? $config['allowed_origins'] : [];
    if (in_array($origin, $allowed, true)) return true;
    $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
    $requestHost = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    return $originHost !== '' && $requestHost !== '' && hash_equals($requestHost, $originHost);
}

function rate_limit_exceeded(string $storage, int $limit): bool {
    if (!is_dir($storage) && !@mkdir($storage, 0750, true)) return true;
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $path = rtrim($storage, '/') . '/rate-' . gmdate('YmdH') . '-' . $ipHash . '.txt';
    $handle = @fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if ($handle) fclose($handle);
        return true;
    }
    rewind($handle);
    $count = (int)stream_get_contents($handle);
    $blocked = $count >= $limit;
    if (!$blocked) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)($count + 1));
        fflush($handle);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $blocked;
}

function post_json(string $url, array $payload): array {
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    return [$status, is_string($body) ? $body : '', $error];
}

function lsq_result(array $decoded): array {
    $status = strtolower(clean_text($decoded['Status'] ?? '', 30));
    $message = $decoded['Message'] ?? null;
    $leadId = '';
    if (is_array($message)) {
        $leadId = clean_text($message['Id'] ?? ($message['ProspectID'] ?? ($message['RelatedId'] ?? '')), 100);
    }
    $success = $status === 'success' && ($leadId !== '' || $message !== null);
    $error = clean_text($decoded['ExceptionMessage'] ?? '', 300);
    if ($error === '' && is_string($message) && !$success) $error = clean_text($message, 300);
    return [$success, $leadId !== '' ? $leadId : 'captured', $error];
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) respond(500, ['ok' => false, 'error' => 'Server configuration missing']);
$config = require $configFile;
if (!is_array($config)) respond(500, ['ok' => false, 'error' => 'Server configuration invalid']);

$courses = is_array($config['courses'] ?? null) ? $config['courses'] : [];
if (!$courses) {
    $slug = clean_text($config['course_slug'] ?? '', 100);
    $value = clean_text($config['course_value'] ?? '', 200);
    if ($slug !== '' && $value !== '') $courses[$slug] = $value;
}

$dryRun = !empty($config['dry_run']);
$accessKey = (string)($config['lsq_access_key'] ?? '');
$secretKey = (string)($config['lsq_secret_key'] ?? '');
$host = rtrim((string)($config['lsq_host'] ?? ''), '/');
$credentialsReady = $accessKey !== '' && $secretKey !== ''
    && strpos($accessKey, 'REPLACE_') !== 0 && strpos($secretKey, 'REPLACE_') !== 0;
$serviceReady = $courses && ($dryRun || (
    $credentialsReady && preg_match('#^https://#i', $host) && function_exists('curl_init')
));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['health'] ?? '') === '1') {
    respond($serviceReady ? 200 : 503, [
        'ok' => $serviceReady,
        'service' => 'lead-capture',
        'mode' => $dryRun ? 'dry-run' : 'live',
    ]);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['ok' => false, 'error' => 'Method not allowed']);
if (!origin_allowed($config)) respond(403, ['ok' => false, 'error' => 'Origin not allowed']);

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== 0) respond(415, ['ok' => false, 'error' => 'JSON required']);
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 20000) respond(413, ['ok' => false, 'error' => 'Request too large']);
$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 20000) respond(413, ['ok' => false, 'error' => 'Request too large']);
$input = json_decode($raw, true);
if (!is_array($input)) respond(400, ['ok' => false, 'error' => 'Invalid JSON']);
if (clean_text($input['website'] ?? '') !== '') respond(422, ['ok' => false, 'error' => 'Invalid submission']);

$name = clean_text($input['name'] ?? '', 100);
$phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? ''));
if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') $phone = substr($phone, 2);
$email = clean_text($input['email'] ?? '', 180);
$profile = clean_text($input['profile'] ?? '', 100);
$course = clean_text($input['course'] ?? '', 100);
$enquiryType = clean_text($input['enquiryType'] ?? '', 100);
$enquiryDetail = clean_text($input['enquiryDetail'] ?? '', 500);
$formLocation = clean_text($input['formLocation'] ?? '', 80);
$attribution = is_array($input['attribution'] ?? null) ? $input['attribution'] : [];
$allowed = is_array($config['allowed_values'] ?? null) ? $config['allowed_values'] : [];

$errors = [];
if (strlen($name) < 2) $errors['name'] = 'Enter your name';
if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) $errors['phone'] = 'Enter a valid 10-digit mobile number';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email';
if (!array_key_exists($course, $courses) || clean_text($courses[$course] ?? '', 200) === '') $errors['course'] = 'Invalid course';
if (!allowed_value($profile, $allowed['profile'] ?? [])) $errors['profile'] = 'Invalid profile';
if (!allowed_value($enquiryType, $allowed['enquiry_type'] ?? [])) $errors['enquiryType'] = 'Invalid enquiry type';
if (!allowed_value($formLocation, $allowed['form_location'] ?? [])) $errors['formLocation'] = 'Invalid form location';
if ($errors) respond(422, ['ok' => false, 'error' => 'Validation failed', 'fields' => $errors]);
if (!$serviceReady) respond(503, ['ok' => false, 'error' => 'Lead service is not configured']);

$storage = (string)($config['storage_dir'] ?? (__DIR__ . '/storage'));
cleanup_storage($storage, max(1, (int)($config['log_retention_days'] ?? 7)));
if (rate_limit_exceeded($storage, max(1, (int)($config['rate_limit'] ?? 12)))) {
    respond(429, ['ok' => false, 'error' => 'Too many requests']);
}

$fieldMap = is_array($config['field_map'] ?? null) ? $config['field_map'] : [];
$mappedCourse = clean_text($courses[$course], 200);
$fields = [];
add_field($fields, 'FirstName', $name);
add_field($fields, 'Phone', $phone);
add_field($fields, 'EmailAddress', $email);
add_field($fields, 'Source', clean_text($config['lsq_source'] ?? '', 100));
add_field($fields, clean_text($config['course_field'] ?? 'mx_Courses', 100), $mappedCourse);
add_field($fields, clean_text($fieldMap['profile'] ?? '', 100), $profile);
add_field($fields, clean_text($fieldMap['enquiry_type'] ?? '', 100), $enquiryType);
add_field($fields, clean_text($fieldMap['enquiry_detail'] ?? '', 100), $enquiryDetail);
add_field($fields, clean_text($fieldMap['form_location'] ?? '', 100), $formLocation);
add_field($fields, 'SourceCampaign', clean_text($attribution['campaign'] ?? '', 200));
add_field($fields, 'SourceMedium', clean_text($attribution['medium'] ?? '', 100));
add_field($fields, 'SourceContent', clean_text($attribution['content'] ?? '', 200));
add_field($fields, 'SourceReferrerURL', clean_url($attribution['referrer'] ?? ''));
add_field($fields, clean_text($fieldMap['utm_term'] ?? '', 100), clean_text($attribution['term'] ?? '', 200));
add_field($fields, clean_text($fieldMap['landing_page'] ?? '', 100), clean_url($attribution['landingPage'] ?? ''));
add_field($fields, clean_text($fieldMap['gclid'] ?? '', 100), clean_click_id($attribution['gclid'] ?? ''));
add_field($fields, clean_text($fieldMap['gbraid'] ?? '', 100), clean_click_id($attribution['gbraid'] ?? ''));
add_field($fields, clean_text($fieldMap['wbraid'] ?? '', 100), clean_click_id($attribution['wbraid'] ?? ''));

$record = [
    'time' => gmdate('c'),
    'course' => $course,
    'course_value' => $mappedCourse,
    'profile' => $profile,
    'enquiry_type' => $enquiryType,
    'form_location' => $formLocation,
    'field_attributes' => array_values(array_map(static function (array $field): string {
        return (string)$field['Attribute'];
    }, $fields)),
    'attribution_keys' => array_values(array_filter(array_keys($attribution), static function ($key) use ($attribution): bool {
        return clean_text($attribution[$key] ?? '') !== '';
    })),
    'phone_hash' => hash('sha256', $phone),
    'email_hash' => $email !== '' ? hash('sha256', strtolower($email)) : '',
];

if ($dryRun) {
    if (!append_jsonl($storage, 'dry-run.jsonl', $record)) {
        respond(500, ['ok' => false, 'error' => 'Dry-run storage is not writable']);
    }
    respond(200, ['ok' => true, 'dryRun' => true]);
}

$url = $host . '/v2/LeadManagement.svc/Lead.Capture'
    . '?accessKey=' . rawurlencode($accessKey)
    . '&secretKey=' . rawurlencode($secretKey)
    . '&SearchBy=Phone';
$softAttributes = ['SourceCampaign', 'SourceMedium', 'SourceContent', 'SourceReferrerURL'];
$leadId = '';
$lastError = 'LeadSquared request failed';

for ($attempt = 1; $attempt <= 3; $attempt++) {
    [$status, $body, $transportError] = post_json($url, $fields);
    $decoded = json_decode($body, true);
    if ($status >= 200 && $status < 300 && is_array($decoded)) {
        [$success, $candidateId, $apiError] = lsq_result($decoded);
        if ($success) {
            $leadId = $candidateId;
            break;
        }
        $lastError = $apiError !== '' ? $apiError : 'LeadSquared returned an unsuccessful response';
    } else {
        $lastError = $transportError !== '' ? $transportError : ('HTTP ' . $status);
    }

    $decodedError = is_array($decoded) ? $decoded : [];
    $message = clean_text($decodedError['ExceptionMessage'] ?? ($decodedError['Message'] ?? ''), 300);
    if ($message !== '' && preg_match('/Attribute "(Source\w+)" is ReadOnly/i', $message, $match)
        && in_array($match[1], $softAttributes, true)) {
        $fields = array_values(array_filter($fields, static function (array $field) use ($match): bool {
            return $field['Attribute'] !== $match[1];
        }));
        $attempt--;
        continue;
    }

    $transient = $status === 0 || in_array($status, [408, 425, 429], true) || $status >= 500;
    if (!$transient) break;
    if ($attempt < 3) usleep($attempt * 300000);
}

if ($leadId === '') {
    append_jsonl($storage, 'failed.jsonl', $record + ['error' => clean_text($lastError, 300)]);
    error_log('LeadSquared capture failed; phone_hash=' . $record['phone_hash'] . '; error=' . clean_text($lastError, 150));
    respond(502, ['ok' => false, 'error' => 'We could not save your request. Please call admissions.']);
}

append_jsonl($storage, 'captured.jsonl', [
    'time' => gmdate('c'),
    'lead_id' => $leadId,
    'phone_hash' => $record['phone_hash'],
    'course' => $course,
    'form_location' => $formLocation,
]);

$activityCode = (int)($config['activity_code'] ?? 0);
if ($activityCode > 0) {
    $activityUrl = $host . '/v2/ProspectActivity.svc/Create'
        . '?accessKey=' . rawurlencode($accessKey)
        . '&secretKey=' . rawurlencode($secretKey);
    post_json($activityUrl, [
        'RelatedProspectId' => $leadId,
        'ActivityEvent' => $activityCode,
        'ActivityNote' => trim($enquiryType . ' — ' . $enquiryDetail),
        'ActivityDateTime' => gmdate('Y-m-d H:i:s'),
    ]);
}

respond(200, ['ok' => true]);
