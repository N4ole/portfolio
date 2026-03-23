<?php
declare(strict_types=1);

function redirect_with_status(string $status): never
{
    header('Location: html/form.html?status=' . urlencode($status));
    exit;
}

function clean_text(string $value, int $maxLen): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? '';

    if (mb_strlen($value) > $maxLen) {
        $value = mb_substr($value, 0, $maxLen);
    }

    return $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('server_error');
}

// Simple honeypot anti-spam field.
if (!empty($_POST['website'] ?? '')) {
    redirect_with_status('success');
}

$nom = clean_text((string) ($_POST['nom'] ?? ''), 100);
$prenom = clean_text((string) ($_POST['prenom'] ?? ''), 100);
$email = clean_text((string) ($_POST['email'] ?? ''), 190);
$message = trim((string) ($_POST['message'] ?? ''));

if ($nom === '' || $prenom === '' || $email === '' || $message === '') {
    redirect_with_status('validation_error');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_status('validation_error');
}

if (mb_strlen($message) > 1000) {
    $message = mb_substr($message, 0, 1000);
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'portfolio';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';

$receiverEmail = getenv('CONTACT_RECEIVER_EMAIL') ?: 'eloandb@icloud.com';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCharset);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS contact_submissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $insert = $pdo->prepare(
        'INSERT INTO contact_submissions (nom, prenom, email)
         VALUES (:nom, :prenom, :email)'
    );

    $insert->execute([
        ':nom' => $nom,
        ':prenom' => $prenom,
        ':email' => $email,
    ]);
} catch (Throwable $e) {
    redirect_with_status('db_error');
}

$subject = 'Nouveau message portfolio - ' . $nom . ' ' . $prenom;
$bodyLines = [
    'Nom: ' . $nom,
    'Prenom: ' . $prenom,
    'Email: ' . $email,
    '',
    'Message:',
    $message,
];

$body = implode(PHP_EOL, $bodyLines);
$fromEmail = 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: Portfolio <' . $fromEmail . '>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . phpversion(),
];

$mailSent = mail($receiverEmail, $subject, $body, implode("\r\n", $headers));

if (!$mailSent) {
    redirect_with_status('mail_error');
}

redirect_with_status('success');
