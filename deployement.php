<?php
declare(strict_types=1);

/*
 * GitHub webhook deploy script.
 *
 * 1) Set a strong secret in the WEBHOOK_SECRET variable below.
 * 2) Configure a GitHub webhook on:
 *    https://your-domain/deployement.php
 * 3) Enable "Just the push event" and set the same secret.
 */

// --- Configuration ---------------------------------------------------------
$WEBHOOK_SECRET = 'GdJt7&^ZNYAyizC3@J$mYNFKYkjyVfVfLmGGDgLsX%oAa$Tb8yhcHQ#a^#cMRaEj^irSre@ku5ocsfjnemrnboxC3RY^3^hZya!g%hDiD*AyU&&J32mNpMVfq$u86WkQ';
$REPO_FULL_NAME = 'N4ole/portfolio';
$TARGET_BRANCH = 'main';
$PROJECT_DIR = __DIR__;
$LOG_FILE = __DIR__ . DIRECTORY_SEPARATOR . 'deploy.log';

// Optional: path to git if needed on your host (example: /usr/bin/git)
$GIT_BIN = 'git';

// --- Helpers ---------------------------------------------------------------
function send_response(int $statusCode, string $message): void
{
	http_response_code($statusCode);
	header('Content-Type: text/plain; charset=utf-8');
	echo $message;
	exit;
}

function write_log(string $logFile, string $message): void
{
	$line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
	file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function run_command(string $command, string $workingDir, ?string &$output = null, ?int &$exitCode = null): bool
{
	$descriptorSpec = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	$process = proc_open($command, $descriptorSpec, $pipes, $workingDir);
	if (!is_resource($process)) {
		$output = 'Unable to start process.';
		$exitCode = 1;
		return false;
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exitCode = proc_close($process);
	$combined = trim($stdout . "\n" . $stderr);
	$output = $combined;

	return $exitCode === 0;
}

// --- Request validation ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	send_response(405, 'Method Not Allowed');
}

if ($WEBHOOK_SECRET === 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET') {
	write_log($LOG_FILE, 'Deployment blocked: default webhook secret still set.');
	send_response(500, 'Server not configured');
}

$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
	send_response(400, 'Empty payload');
}

$signatureHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (strpos($signatureHeader, 'sha256=') !== 0) {
	write_log($LOG_FILE, 'Rejected request: missing or invalid signature header.');
	send_response(401, 'Invalid signature header');
}

$receivedSignature = substr($signatureHeader, 7);
$computedSignature = hash_hmac('sha256', $payload, $WEBHOOK_SECRET);

if (!hash_equals($computedSignature, $receivedSignature)) {
	write_log($LOG_FILE, 'Rejected request: signature mismatch.');
	send_response(401, 'Invalid signature');
}

$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event !== 'push') {
	send_response(200, 'Event ignored');
}

$data = json_decode($payload, true);
if (!is_array($data)) {
	send_response(400, 'Invalid JSON payload');
}

$repoFullName = $data['repository']['full_name'] ?? '';
if ($repoFullName !== $REPO_FULL_NAME) {
	write_log($LOG_FILE, sprintf('Ignored push from repo "%s".', $repoFullName));
	send_response(200, 'Repository ignored');
}

$ref = $data['ref'] ?? '';
$expectedRef = 'refs/heads/' . $TARGET_BRANCH;
if ($ref !== $expectedRef) {
	write_log($LOG_FILE, sprintf('Ignored push on ref "%s".', $ref));
	send_response(200, 'Branch ignored');
}

// --- Deployment ------------------------------------------------------------
$commands = [
	escapeshellcmd($GIT_BIN) . ' fetch --all --prune',
	escapeshellcmd($GIT_BIN) . ' reset --hard origin/' . escapeshellarg($TARGET_BRANCH),
];

$allOutput = [];
foreach ($commands as $cmd) {
	$ok = run_command($cmd, $PROJECT_DIR, $cmdOutput, $cmdExitCode);
	$allOutput[] = '$ ' . $cmd;
	$allOutput[] = (string) $cmdOutput;

	if (!$ok) {
		write_log($LOG_FILE, sprintf("Deployment failed (exit %d): %s\n%s", (int) $cmdExitCode, $cmd, (string) $cmdOutput));
		send_response(500, 'Deployment failed');
	}
}

write_log($LOG_FILE, "Deployment successful.\n" . implode("\n", $allOutput));
send_response(200, 'Deployment successful');

