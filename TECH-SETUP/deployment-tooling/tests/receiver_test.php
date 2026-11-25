<?php
declare(strict_types=1);

define('LANDING_DEPLOY_RECEIVER_NO_MAIN', true);
require dirname(__DIR__) . '/receive.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function makePayload(string $site, array $files, string $revision = '0123456789abcdef0123456789abcdef01234567'): string
{
    $entries = [];
    foreach ($files as $path => $content) {
        $entries[] = [
            'path' => $path,
            'sha256' => hash('sha256', $content),
            'contentBase64' => base64_encode($content),
        ];
    }
    return json_encode(['site' => $site, 'revision' => $revision, 'files' => $entries], JSON_THROW_ON_ERROR);
}

function expectFailure(callable $operation, string $needle): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        expect(str_contains($exception->getMessage(), $needle), 'Failure was unexpected: ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('Expected failure containing: ' . $needle);
}

$base = realpath(sys_get_temp_dir()) . '/landing-deploy-test-' . bin2hex(random_bytes(6));
$root = $base . '/site';
$backupRoot = $base . '/backups';
$lockPath = $base . '/deploy.lock';
mkdir($base, 0700, true);
mkdir($root, 0755, true);
file_put_contents($lockPath, '');

try {
    $site = 'gulf-careers';
    $initial = makePayload($site, [
        'index.html' => '<!doctype html><title>Initial</title>',
        'assets/app.js' => 'export const version = 1;',
        'assets/site.css' => 'body { color: #123; }',
        'assets/legacy.txt' => 'kept across deployments',
    ]);
    $receiver = new DeployReceiver([$site => $root], $backupRoot, $lockPath);

    $dryRun = $receiver->run($site, 'dry-run', $initial);
    expect(($dryRun['dryRun'] ?? false) === true, 'Dry run should be marked as a dry run.');
    expect(!file_exists($root . '/index.html'), 'Dry run wrote an index file.');
    expect(!file_exists($backupRoot), 'Dry run created a backup directory.');
    expect(!file_exists($root . '/deploy-version.json'), 'Dry run wrote a deployment marker.');

    $deployed = $receiver->run($site, 'deploy', $initial);
    expect(($deployed['ok'] ?? false) === true, 'Initial deployment did not succeed.');
    expect(file_get_contents($root . '/index.html') === '<!doctype html><title>Initial</title>', 'Initial page bytes differ.');
    expect(file_get_contents($root . '/assets/app.js') === 'export const version = 1;', 'Nested asset was not installed.');
    $marker = json_decode((string)file_get_contents($root . '/deploy-version.json'), true, 32, JSON_THROW_ON_ERROR);
    expect($marker['revision'] === '0123456789abcdef0123456789abcdef01234567', 'Receiver marker has the wrong revision.');
    $probe = $receiver->run($site, 'probe');
    expect($probe['revision'] === $marker['revision'], 'Probe did not return the deployed revision.');

    $failedRelease = makePayload($site, [
        'index.html' => '<!doctype html><title>New</title>',
        'assets/new.css' => 'body { color: #abc; }',
        'assets/site.css' => 'body { color: #456; }',
    ], '1123456789abcdef0123456789abcdef01234567');
    $failingReceiver = new DeployReceiver(
        [$site => $root],
        $backupRoot,
        $lockPath,
        static function (string $path, int $count): void {
            if ($count === 2) throw new RuntimeException('test promotion failure');
        }
    );
    expectFailure(static fn() => $failingReceiver->run($site, 'deploy', $failedRelease), 'prior files were restored');
    expect(file_get_contents($root . '/index.html') === '<!doctype html><title>Initial</title>', 'Rollback did not preserve the prior index.');
    expect(file_get_contents($root . '/assets/site.css') === 'body { color: #123; }', 'Rollback did not restore an overwritten file.');
    expect(!file_exists($root . '/assets/new.css'), 'Rollback did not remove a newly added file.');

    $updated = $receiver->run($site, 'deploy', makePayload($site, [
        'index.html' => '<!doctype html><title>Updated</title>',
        'assets/app.js' => 'export const version = 2;',
        'assets/site.css' => 'body { color: #456; }',
    ], '2123456789abcdef0123456789abcdef01234567'));
    expect(file_get_contents($root . '/index.html') === '<!doctype html><title>Updated</title>', 'Successful update did not promote the new index.');
    expect(file_get_contents($root . '/assets/legacy.txt') === 'kept across deployments', 'Deployment deleted a file omitted from the new manifest.');
    $savedOldIndex = $backupRoot . '/' . $site . '/' . $updated['backupId'] . '/index.html';
    expect(file_get_contents($savedOldIndex) === '<!doctype html><title>Initial</title>', 'Backup did not preserve the exact replaced index.');

    $tallyRoot = $base . '/tally-site';
    mkdir($tallyRoot, 0755, true);
    $tallyReceiver = new DeployReceiver(['tally-prime-kerala' => $tallyRoot], $backupRoot, $lockPath);
    $tallyPayload = makePayload('tally-prime-kerala', [
        'index.html' => '<title>Tally</title>',
        'icons.svg' => '<svg></svg>',
        'photos/learners/person.jpg' => 'image bytes',
    ]);
    expect(($tallyReceiver->run('tally-prime-kerala', 'dry-run', $tallyPayload)['dryRun'] ?? false) === true, 'Tally site-specific directories were not allowlisted.');
    expectFailure(static fn() => $tallyReceiver->run('tally-prime-kerala', 'dry-run', makePayload('tally-prime-kerala', ['index.html' => '<title>Tally</title>', 'tracking.js' => 'bad'])), 'server-side site allowlist');

    $outsideFile = $base . '/outside.js';
    file_put_contents($outsideFile, 'outside contents');
    if (@symlink($outsideFile, $root . '/assets/linked.js')) {
        expectFailure(static fn() => $receiver->run($site, 'dry-run', makePayload($site, ['index.html' => '<title>Safe</title>', 'assets/linked.js' => 'replacement'])), 'symlink');
        expect(file_get_contents($outsideFile) === 'outside contents', 'Symlink validation modified an outside file.');
        unlink($root . '/assets/linked.js');
    }

    $unsafeCases = [
        [['../api/config.php' => 'bad'], 'forbidden'],
        [['api/config.json' => 'bad'], 'forbidden'],
        [['storage/data.txt' => 'bad'], 'forbidden'],
        [['.htaccess' => 'bad'], 'forbidden'],
        [['index.php' => 'bad'], 'not allowed'],
        [['site-config.js' => 'const password = "' . 'ThisIsAnExample' . 'SecretValue' . '";'], 'secret pattern'],
    ];
    foreach ($unsafeCases as [$unsafeFile, $reason]) {
        $unsafe = makePayload($site, ['index.html' => '<title>Safe</title>'] + $unsafeFile);
        expectFailure(static fn() => $receiver->run($site, 'dry-run', $unsafe), $reason);
    }

    $oversizedFile = makePayload($site, ['index.html' => str_repeat('x', 15 * 1024 * 1024 + 1)]);
    expectFailure(static fn() => $receiver->run($site, 'dry-run', $oversizedFile), 'per-file size limit');
    unset($oversizedFile);

    $wrongSite = makePayload('online-courses', ['index.html' => '<title>Wrong site</title>']);
    expectFailure(static fn() => $receiver->run($site, 'dry-run', $wrongSite), 'does not match');
    fwrite(STDOUT, "receiver tests passed\n");
} finally {
    removeTree($base);
}

function removeTree(string $path): void
{
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            removeTree($path . '/' . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}
