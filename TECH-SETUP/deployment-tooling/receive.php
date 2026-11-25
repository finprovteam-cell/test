<?php
declare(strict_types=1);

/**
 * Restricted static-site deployment receiver.
 *
 * Install this file outside public_html and use it as the forced command for a
 * site-specific SSH key. The destination map below is deliberately compiled
 * into the receiver; request data can never select a filesystem root.
 */
final class DeployReceiver
{
    private const MAX_REQUEST_BYTES = 42 * 1024 * 1024;
    private const MAX_TOTAL_FILE_BYTES = 30 * 1024 * 1024;
    private const MAX_FILE_BYTES = 15 * 1024 * 1024;
    private const MAX_FILES = 2000;

    private const ALLOWED_EXTENSIONS = [
        'html', 'js', 'css', 'png', 'jpg', 'jpeg', 'webp', 'svg', 'ico',
        'txt', 'pdf', 'json', 'woff2', 'woff', 'ttf',
    ];

    /** Server-side maximum path policy, independent of the repository manifest. */
    private const SITE_FILE_POLICIES = [
        'gulf-careers' => [
            'rootFiles' => ['index.html', 'thank-you/index.html', 'tracking.js', 'site-config.js', 'favicon.ico', 'favicon.png', 'favicon.svg', 'robots.txt'],
            'prefixes' => ['assets/'],
        ],
        'online-courses' => [
            'rootFiles' => ['index.html', 'thank-you/index.html', 'tracking.js', 'site-config.js', 'favicon.ico', 'favicon.png', 'favicon.svg', 'robots.txt'],
            'prefixes' => ['assets/'],
        ],
        'tally-prime-kerala' => [
            'rootFiles' => ['index.html', 'favicon.ico', 'favicon.png', 'favicon.svg', 'icons.svg', 'robots.txt'],
            'prefixes' => ['assets/', 'brand/', 'brochures/', 'logos/', 'photos/'],
        ],
    ];

    /** @var array<string, string> */
    private array $siteRoots;
    private string $backupRoot;
    private string $lockPath;
    /** @var null|callable(string, int): void Test-only fault injection hook. */
    private $afterPromoteHook;

    /**
     * The optional hook exists only so the isolated test suite can simulate a
     * mid-transaction failure. Production construction never supplies it.
     *
     * @param array<string, string> $siteRoots
     * @param null|callable(string, int): void $afterPromoteHook
     */
    public function __construct(
        array $siteRoots,
        string $backupRoot,
        string $lockPath,
        ?callable $afterPromoteHook = null
    ) {
        $this->siteRoots = $siteRoots;
        $this->backupRoot = rtrim($backupRoot, '/');
        $this->lockPath = $lockPath;
        $this->afterPromoteHook = $afterPromoteHook;
    }

    /** @return array<string, mixed> */
    public function run(string $site, string $mode = 'deploy', ?string $input = null): array
    {
        if (!isset($this->siteRoots[$site])) {
            throw new RuntimeException('Unknown site.');
        }
        if (!in_array($mode, ['deploy', 'dry-run', 'probe'], true)) {
            throw new RuntimeException('Unsupported operation.');
        }

        $lock = $this->openLock();
        try {
            $root = $this->assertSiteRoot($site);

            if ($mode === 'probe') {
                return $this->probe($site, $root);
            }
            $this->assertBackupLocation($root);

            $rawInput = $input ?? $this->readRequest();
            $release = $this->parseAndValidateRelease($site, $rawInput);
            $this->validateTargets($root, $release);
            $summary = [
                'ok' => true,
                'site' => $site,
                'revision' => $release['revision'],
                'fileCount' => count($release['files']),
                'totalBytes' => $release['totalBytes'],
                'paths' => array_map(static fn(array $file): string => $file['path'], $release['files']),
            ];

            if ($mode === 'dry-run') {
                $summary['dryRun'] = true;
                return $summary;
            }

            $backupId = $this->promote($site, $root, $release);
            $summary['dryRun'] = false;
            $summary['backupId'] = $backupId;
            return $summary;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return resource */
    private function openLock()
    {
        $stat = @lstat($this->lockPath);
        if ($stat === false || $this->isSymlinkStat($stat) || !$this->isRegularStat($stat)) {
            throw new RuntimeException('Deployment lock file is missing or unsafe.');
        }
        $lockParent = dirname($this->lockPath);
        $realLockParent = realpath($lockParent);
        if ($realLockParent === false || $realLockParent !== $lockParent) {
            throw new RuntimeException('Deployment lock path is not canonical.');
        }
        foreach ($this->siteRoots as $siteRoot) {
            if ($this->isWithin($this->lockPath, rtrim($siteRoot, '/'))) {
                throw new RuntimeException('Deployment lock file must be outside the web roots.');
            }
        }
        $handle = @fopen($this->lockPath, 'rb');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Could not acquire deployment lock.');
        }
        return $handle;
    }

    private function assertSiteRoot(string $site): string
    {
        $configured = $this->siteRoots[$site];
        $real = realpath($configured);
        $stat = @lstat($configured);
        if ($real === false || $stat === false || $this->isSymlinkStat($stat) || !$this->isDirectoryStat($stat)) {
            throw new RuntimeException('Configured site root is missing or unsafe.');
        }
        if ($real !== rtrim($configured, '/')) {
            throw new RuntimeException('Configured site root is not canonical.');
        }
        return $real;
    }

    private function assertBackupLocation(string $siteRoot): void
    {
        if ($this->backupRoot === '') {
            throw new RuntimeException('Backup directory must be outside the site root.');
        }
        foreach ($this->siteRoots as $configuredRoot) {
            $root = rtrim($configuredRoot, '/');
            if ($this->isWithin($this->backupRoot, $root) || $this->isWithin($root, $this->backupRoot)) {
                throw new RuntimeException('Backup directory must be outside every web root.');
            }
        }

        $parent = dirname($this->backupRoot);
        $realParent = realpath($parent);
        $parentStat = @lstat($parent);
        if ($realParent === false || $parentStat === false || $this->isSymlinkStat($parentStat) || !$this->isDirectoryStat($parentStat)) {
            throw new RuntimeException('Backup parent directory is missing or unsafe.');
        }
        if ($realParent !== $parent) {
            throw new RuntimeException('Backup parent path is not canonical.');
        }
        if (!is_writable($parent)) {
            throw new RuntimeException('Backup parent directory is not writable by the deploy account.');
        }
        $backupStat = @lstat($this->backupRoot);
        if ($backupStat !== false && ($this->isSymlinkStat($backupStat) || !$this->isDirectoryStat($backupStat) || realpath($this->backupRoot) !== $this->backupRoot)) {
            throw new RuntimeException('Backup directory is unsafe.');
        }
        if ($backupStat !== false && !is_writable($this->backupRoot)) {
            throw new RuntimeException('Backup directory is not writable by the deploy account.');
        }
    }

    private function validateTargets(string $root, array $release): void
    {
        foreach ($release['files'] as $file) {
            $target = $root . '/' . $file['path'];
            $this->assertSafeTargetPath($root, $file['path'], false);
            $stat = @lstat($target);
            if ($stat !== false) {
                if ($this->isSymlinkStat($stat) || !$this->isRegularStat($stat) || $stat['size'] > self::MAX_FILE_BYTES) {
                    throw new RuntimeException('An existing target is not a safe regular file.');
                }
                $contents = @file_get_contents($target);
                if ($contents === false || strlen($contents) !== $stat['size']) {
                    throw new RuntimeException('An existing target cannot be safely backed up.');
                }
            }
            $writableParent = dirname($target);
            while (@lstat($writableParent) === false) {
                $nextParent = dirname($writableParent);
                if ($nextParent === $writableParent) break;
                $writableParent = $nextParent;
            }
            $parentStat = @lstat($writableParent);
            if ($parentStat === false || $this->isSymlinkStat($parentStat) || !$this->isDirectoryStat($parentStat) || !is_writable($writableParent)) {
                throw new RuntimeException('A release target directory is not writable by the deploy account.');
            }
        }
        $marker = $root . '/deploy-version.json';
        $markerStat = @lstat($marker);
        if ($markerStat !== false && ($this->isSymlinkStat($markerStat) || !$this->isRegularStat($markerStat) || $markerStat['size'] > 8192)) {
            throw new RuntimeException('Existing deployment marker is unsafe.');
        }
        if (!is_writable($root)) {
            throw new RuntimeException('Site root is not writable by the deploy account.');
        }
    }

    /** @return array<string, mixed> */
    private function probe(string $site, string $root): array
    {
        $markerPath = $root . '/deploy-version.json';
        $stat = @lstat($markerPath);
        if ($stat === false) {
            return ['ok' => true, 'site' => $site, 'revision' => null, 'deployedAt' => null];
        }
        if ($this->isSymlinkStat($stat) || !$this->isRegularStat($stat) || $stat['size'] > 8192) {
            throw new RuntimeException('Deployment marker is unsafe.');
        }
        $contents = @file_get_contents($markerPath);
        if ($contents === false) {
            throw new RuntimeException('Could not read deployment marker.');
        }
        try {
            $marker = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Deployment marker is invalid JSON.');
        }
        if (!is_array($marker) || ($marker['site'] ?? null) !== $site || !is_string($marker['revision'] ?? null)) {
            throw new RuntimeException('Deployment marker is invalid.');
        }
        return [
            'ok' => true,
            'site' => $site,
            'revision' => $marker['revision'],
            'deployedAt' => is_string($marker['deployedAt'] ?? null) ? $marker['deployedAt'] : null,
        ];
    }

    private function readRequest(): string
    {
        $stream = fopen('php://stdin', 'rb');
        if ($stream === false) {
            throw new RuntimeException('Could not read deployment request.');
        }
        $contents = stream_get_contents($stream, self::MAX_REQUEST_BYTES + 1);
        fclose($stream);
        if ($contents === false) throw new RuntimeException('Could not read deployment request.');
        if (strlen($contents) > self::MAX_REQUEST_BYTES) throw new RuntimeException('Deployment request exceeds the size limit.');
        if ($contents === '') {
            throw new RuntimeException('Deployment request is empty.');
        }
        return $contents;
    }

    /** @return array{revision:string,files:array<int,array{path:string,sha256:string,content:string,size:int}>,totalBytes:int} */
    private function parseAndValidateRelease(string $site, string &$rawInput): array
    {
        if (strlen($rawInput) > self::MAX_REQUEST_BYTES) {
            throw new RuntimeException('Deployment request exceeds the size limit.');
        }
        try {
            $payload = json_decode($rawInput, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Deployment request is invalid JSON.');
        }
        unset($rawInput);
        if (!is_array($payload) || !$this->hasExactKeys($payload, ['site', 'revision', 'files'])) {
            throw new RuntimeException('Deployment request has an invalid shape.');
        }
        if (($payload['site'] ?? null) !== $site) {
            throw new RuntimeException('Deployment request site does not match the SSH key.');
        }
        $revision = $payload['revision'] ?? null;
        if (!is_string($revision) || !preg_match('/\A[a-f0-9]{40}\z/', $revision)) {
            throw new RuntimeException('Revision must be a 40-character lowercase Git commit hash.');
        }
        $incomingFiles =& $payload['files'];
        if (!is_array($incomingFiles) || !$this->isList($incomingFiles) || count($incomingFiles) === 0 || count($incomingFiles) > self::MAX_FILES) {
            throw new RuntimeException('Files must be a non-empty list within the file-count limit.');
        }

        $files = [];
        $seen = [];
        $totalBytes = 0;
        $hasRootIndex = false;
        foreach ($incomingFiles as $fileIndex => $incoming) {
            if (!is_array($incoming) || !$this->hasExactKeys($incoming, ['path', 'sha256', 'contentBase64'])) {
                throw new RuntimeException('A release file entry has an invalid shape.');
            }
            $path = $incoming['path'] ?? null;
            $sha256 = $incoming['sha256'] ?? null;
            $encoded = $incoming['contentBase64'] ?? null;
            if (!is_string($path) || !is_string($sha256) || !is_string($encoded)) {
                throw new RuntimeException('A release file entry contains invalid values.');
            }
            $this->validateRelativePath($site, $path);
            if (isset($seen[$path])) {
                throw new RuntimeException('Duplicate release file path.');
            }
            $seen[$path] = true;
            if ($path === 'deploy-version.json') {
                throw new RuntimeException('deploy-version.json is receiver-managed.');
            }
            if (!preg_match('/\A[a-f0-9]{64}\z/', $sha256)) {
                throw new RuntimeException('A release file has an invalid SHA-256 digest.');
            }
            $content = base64_decode($encoded, true);
            if ($content === false || base64_encode($content) !== $encoded) {
                throw new RuntimeException('A release file has invalid base64 content.');
            }
            $size = strlen($content);
            if ($size > self::MAX_FILE_BYTES) {
                throw new RuntimeException('A release file exceeds the per-file size limit.');
            }
            $totalBytes += $size;
            if ($totalBytes > self::MAX_TOTAL_FILE_BYTES) {
                throw new RuntimeException('Release files exceed the total size limit.');
            }
            if (!hash_equals($sha256, hash('sha256', $content))) {
                throw new RuntimeException('A release file hash does not match its content.');
            }
            $this->rejectSecretContent($content);
            if ($path === 'index.html') {
                $hasRootIndex = true;
            }
            $files[] = ['path' => $path, 'sha256' => $sha256, 'content' => $content, 'size' => $size];
            unset($payload['files'][$fileIndex], $incoming, $encoded, $content);
        }
        unset($incomingFiles, $payload);
        if (!$hasRootIndex) {
            throw new RuntimeException('Release must include root index.html.');
        }

        usort($files, static function (array $left, array $right): int {
            $leftHtml = str_ends_with(strtolower($left['path']), '.html');
            $rightHtml = str_ends_with(strtolower($right['path']), '.html');
            if ($leftHtml !== $rightHtml) {
                return $leftHtml ? 1 : -1;
            }
            if ($left['path'] === 'index.html' && $right['path'] !== 'index.html') {
                return 1;
            }
            if ($right['path'] === 'index.html' && $left['path'] !== 'index.html') {
                return -1;
            }
            return strcmp($left['path'], $right['path']);
        });

        return ['revision' => $revision, 'files' => $files, 'totalBytes' => $totalBytes];
    }

    private function validateRelativePath(string $site, string $path): void
    {
        if ($path === '' || strlen($path) > 240 || str_contains($path, '\\') || str_contains($path, "\0") || preg_match('/[\x00-\x1f\x7f]/', $path)) {
            throw new RuntimeException('A release path is invalid.');
        }
        if (str_starts_with($path, '/') || preg_match('/\A[A-Za-z]:/', $path)) {
            throw new RuntimeException('Absolute release paths are forbidden.');
        }
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.') || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $part)) {
                throw new RuntimeException('A release path contains a forbidden component.');
            }
            if (in_array(strtolower($part), ['api', 'storage'], true)) {
                throw new RuntimeException('API and storage paths are forbidden.');
            }
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('A release file type is not allowed.');
        }
        if (strtolower(basename($path)) === 'config.php') {
            throw new RuntimeException('PHP configuration files are forbidden.');
        }
        $policy = self::SITE_FILE_POLICIES[$site] ?? null;
        if (!is_array($policy)) {
            throw new RuntimeException('No server-side file policy exists for this site.');
        }
        $allowed = in_array($path, $policy['rootFiles'], true);
        foreach ($policy['prefixes'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new RuntimeException('Release path is outside the server-side site allowlist.');
        }
    }

    private function rejectSecretContent(string $content): void
    {
        $patterns = [
            '/-----BEGIN(?: [A-Z0-9]+)* PRIVATE KEY-----/',
            '/\bAKIA[0-9A-Z]{16}\b/',
            '/\b(?:gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,})\b/',
            '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
            '/\bsk_(?:live|test)_[A-Za-z0-9]{12,}\b/',
            '/\bBearer\s+[A-Za-z0-9._-]{24,}\b/i',
            '/\b(?:api[_-]?key|access[_-]?token|client[_-]?secret|password)\s*[:=]\s*["\'][^"\']{16,}["\']/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                throw new RuntimeException('Release content matches a blocked secret pattern.');
            }
        }
    }

    /** @param array<int|string, mixed> $value @param array<int|string, string> $keys */
    private function hasExactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        return $actual === $keys;
    }

    /** @param array<int|string, mixed> $value */
    private function isList(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }

    private function promote(string $site, string $root, array $release): string
    {
        $backupParent = $this->backupRoot . '/' . $site;
        $this->ensureDirectoryChain($backupParent, 0700);
        $backupId = gmdate('Ymd\THis\Z') . '-' . substr($release['revision'], 0, 12) . '-' . bin2hex(random_bytes(4));
        $backupDir = $backupParent . '/' . $backupId;
        if (!@mkdir($backupDir, 0700)) {
            throw new RuntimeException('Could not create the versioned backup directory.');
        }

        $previous = [];
        foreach ($release['files'] as $file) {
            $target = $root . '/' . $file['path'];
            $this->assertSafeTargetPath($root, $file['path'], false);
            $stat = @lstat($target);
            if ($stat === false) {
                $previous[] = ['path' => $file['path'], 'existed' => false, 'sha256' => null, 'backupDir' => $backupDir];
                continue;
            }
            if ($this->isSymlinkStat($stat) || !$this->isRegularStat($stat) || $stat['size'] > self::MAX_FILE_BYTES) {
                throw new RuntimeException('An existing target is not a safe regular file.');
            }
            $oldContents = @file_get_contents($target);
            if ($oldContents === false || strlen($oldContents) !== $stat['size']) {
                throw new RuntimeException('Could not safely read an existing target.');
            }
            $oldHash = hash('sha256', $oldContents);
            $backupFile = $backupDir . '/' . $file['path'];
            $this->ensureDirectoryChain(dirname($backupFile), 0700);
            $this->writeExclusiveFile($backupFile, $oldContents, 0600);
            $previous[] = ['path' => $file['path'], 'existed' => true, 'sha256' => $oldHash, 'backupDir' => $backupDir];
        }
        $backupManifest = [
            'schemaVersion' => 1,
            'site' => $site,
            'revision' => $release['revision'],
            'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'files' => array_map(static fn(array $old): array => ['path' => $old['path'], 'existed' => $old['existed'], 'sha256' => $old['sha256']], $previous),
        ];
        $this->writeExclusiveFile($backupDir . '/backup-manifest.json', json_encode($backupManifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n", 0600);

        $createdDirectories = [];
        $staged = [];
        $promoted = [];
        $markerTemp = null;
        try {
            foreach ($release['files'] as $file) {
                $target = $root . '/' . $file['path'];
                $parent = dirname($target);
                $this->ensureDirectoryChain($parent, 0755, $createdDirectories);
                $this->assertSafeTargetPath($root, $file['path'], true);
                $temp = $parent . '/.landing-deploy-' . bin2hex(random_bytes(8)) . '.tmp';
                $this->writeExclusiveFile($temp, $file['content'], 0644);
                $staged[$file['path']] = $temp;
            }

            foreach ($release['files'] as $index => $file) {
                $target = $root . '/' . $file['path'];
                $this->assertSafeTargetPath($root, $file['path'], true);
                $stat = @lstat($target);
                $wasPresent = false;
                $oldHash = null;
                foreach ($previous as $old) {
                    if ($old['path'] === $file['path']) {
                        $wasPresent = $old['existed'];
                        $oldHash = $old['sha256'];
                        break;
                    }
                }
                if ($stat !== false) {
                    if ($this->isSymlinkStat($stat) || !$this->isRegularStat($stat) || !$wasPresent) {
                        throw new RuntimeException('A target changed after validation; deployment stopped.');
                    }
                    $currentHash = hash_file('sha256', $target);
                    if ($currentHash === false || !is_string($oldHash) || !hash_equals($oldHash, $currentHash)) {
                        throw new RuntimeException('A target changed after backup; deployment stopped.');
                    }
                } elseif ($wasPresent) {
                    throw new RuntimeException('A target disappeared after backup; deployment stopped.');
                }
                if (!@rename($staged[$file['path']], $target)) {
                    throw new RuntimeException('Could not atomically promote a release file.');
                }
                unset($staged[$file['path']]);
                $promoted[] = $file;
                if ($this->afterPromoteHook !== null) {
                    ($this->afterPromoteHook)($file['path'], count($promoted));
                }
            }

            $markerFiles = array_map(static fn(array $file): array => ['path' => $file['path'], 'sha256' => $file['sha256'], 'size' => $file['size']], $release['files']);
            $marker = [
                'schemaVersion' => 1,
                'site' => $site,
                'revision' => $release['revision'],
                'deployedAt' => gmdate('Y-m-d\TH:i:s\Z'),
                'fileCount' => count($release['files']),
                'manifestSha256' => hash('sha256', json_encode($markerFiles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            ];
            $markerPath = $root . '/deploy-version.json';
            $markerStat = @lstat($markerPath);
            if ($markerStat !== false && ($this->isSymlinkStat($markerStat) || !$this->isRegularStat($markerStat))) {
                throw new RuntimeException('Existing deployment marker is unsafe.');
            }
            $markerTemp = $root . '/.landing-deploy-marker-' . bin2hex(random_bytes(8)) . '.tmp';
            $this->writeExclusiveFile($markerTemp, json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n", 0644);
            if (!@rename($markerTemp, $markerPath)) {
                @unlink($markerTemp);
                throw new RuntimeException('Could not atomically update deployment marker.');
            }
            $markerTemp = null;
        } catch (Throwable $exception) {
            if (is_string($markerTemp)) {
                @unlink($markerTemp);
            }
            foreach ($staged as $temp) {
                @unlink($temp);
            }
            $rollbackErrors = $this->rollback($root, $release['files'], $previous, $promoted);
            $this->removeEmptyDirectories($createdDirectories);
            if ($rollbackErrors !== []) {
                throw new RuntimeException('Deployment failed and rollback needs attention: ' . implode('; ', $rollbackErrors));
            }
            throw new RuntimeException('Deployment failed; prior files were restored. ' . $exception->getMessage());
        }

        return $backupId;
    }

    /** @return list<string> */
    private function rollback(string $root, array $releaseFiles, array $previous, array $promoted): array
    {
        $errors = [];
        $byPath = [];
        foreach ($previous as $old) {
            $byPath[$old['path']] = $old;
        }
        $incomingByPath = [];
        foreach ($releaseFiles as $file) {
            $incomingByPath[$file['path']] = $file;
        }
        foreach (array_reverse($promoted) as $file) {
            $path = $file['path'];
            $target = $root . '/' . $path;
            try {
                $this->assertSafeTargetPath($root, $path, true);
                $stat = @lstat($target);
                if ($stat === false) {
                    $errors[] = $path . ': promoted file disappeared';
                    continue;
                }
                if ($this->isSymlinkStat($stat) || !$this->isRegularStat($stat)) {
                    $errors[] = $path . ': target became unsafe';
                    continue;
                }
                $old = $byPath[$path];
                if ($old['existed']) {
                    $backupFile = $old['backupDir'] . '/' . $path;
                    $oldContents = @file_get_contents($backupFile);
                    if ($oldContents === false || !hash_equals((string)$old['sha256'], hash('sha256', $oldContents))) {
                        $errors[] = $path . ': backup is missing or corrupt';
                        continue;
                    }
                    $temp = dirname($target) . '/.landing-deploy-rollback-' . bin2hex(random_bytes(8)) . '.tmp';
                    $this->writeExclusiveFile($temp, $oldContents, 0644);
                    if (!@rename($temp, $target)) {
                        @unlink($temp);
                        $errors[] = $path . ': could not restore backup';
                    }
                } else {
                    $currentHash = hash_file('sha256', $target);
                    if ($currentHash === false || !hash_equals($incomingByPath[$path]['sha256'], $currentHash) || !@unlink($target)) {
                        $errors[] = $path . ': could not safely remove newly created file';
                    }
                }
            } catch (Throwable $exception) {
                $errors[] = $path . ': restore failed';
            }
        }
        return $errors;
    }

    private function assertSafeTargetPath(string $root, string $relativePath, bool $allowMissingParents): void
    {
        $rootStat = @lstat($root);
        if ($rootStat === false || $this->isSymlinkStat($rootStat) || !$this->isDirectoryStat($rootStat) || realpath($root) !== $root) {
            throw new RuntimeException('Configured site root changed during deployment.');
        }
        $parts = explode('/', $relativePath);
        $current = $root;
        $last = count($parts) - 1;
        foreach ($parts as $index => $part) {
            $current .= '/' . $part;
            $stat = @lstat($current);
            $isFinal = $index === $last;
            if ($stat === false) {
                if (!$allowMissingParents && !$isFinal) {
                    return;
                }
                continue;
            }
            if ($this->isSymlinkStat($stat)) {
                throw new RuntimeException('A release target contains a symlink.');
            }
            if (!$isFinal && !$this->isDirectoryStat($stat)) {
                throw new RuntimeException('A release parent path is not a directory.');
            }
            if (!$isFinal && realpath($current) !== $current) {
                throw new RuntimeException('A release parent path is not canonical.');
            }
            if ($isFinal && !$this->isRegularStat($stat)) {
                throw new RuntimeException('A release target is not a regular file.');
            }
        }
    }

    /** @param list<string>|null $createdDirectories */
    private function ensureDirectoryChain(string $directory, int $mode, ?array &$createdDirectories = null): void
    {
        $directory = rtrim($directory, '/');
        if ($directory === '') {
            throw new RuntimeException('Invalid directory path.');
        }
        $parts = explode('/', ltrim($directory, '/'));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            $stat = @lstat($current);
            if ($stat === false) {
                if (!@mkdir($current, $mode)) {
                    $stat = @lstat($current);
                    if ($stat === false) {
                        throw new RuntimeException('Could not create a required directory.');
                    }
                } else {
                    $createdDirectories[] = $current;
                    $stat = @lstat($current);
                }
            }
            if ($stat === false || $this->isSymlinkStat($stat) || !$this->isDirectoryStat($stat)) {
                throw new RuntimeException('A required directory is unsafe.');
            }
            $real = realpath($current);
            if ($real === false || $real !== $current) {
                throw new RuntimeException('A required directory path is not canonical.');
            }
        }
    }

    private function writeExclusiveFile(string $path, string $contents, int $mode): void
    {
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Could not create a staging or backup file.');
        }
        $length = strlen($contents);
        $written = 0;
        try {
            while ($written < $length) {
                $chunk = substr($contents, $written, 65536);
                $count = fwrite($handle, $chunk);
                if ($count === false || $count === 0) {
                    throw new RuntimeException('Could not write a staging or backup file.');
                }
                $written += $count;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('Could not flush a staging or backup file.');
            }
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($path);
            throw $exception;
        } finally {
            if (is_resource($handle)) fclose($handle);
        }
        if (!@chmod($path, $mode)) {
            @unlink($path);
            throw new RuntimeException('Could not set safe file permissions.');
        }
    }

    /** @param list<string> $directories */
    private function removeEmptyDirectories(array $directories): void
    {
        foreach (array_reverse($directories) as $directory) {
            @rmdir($directory);
        }
    }

    private function isWithin(string $candidate, string $root): bool
    {
        $root = rtrim($root, '/');
        $candidate = rtrim($candidate, '/');
        return $candidate === $root || str_starts_with($candidate, $root . '/');
    }

    /** @param array<string, mixed> $stat */
    private function isSymlinkStat(array $stat): bool
    {
        return (($stat['mode'] ?? 0) & 0170000) === 0120000;
    }

    /** @param array<string, mixed> $stat */
    private function isDirectoryStat(array $stat): bool
    {
        return (($stat['mode'] ?? 0) & 0170000) === 0040000;
    }

    /** @param array<string, mixed> $stat */
    private function isRegularStat(array $stat): bool
    {
        return (($stat['mode'] ?? 0) & 0170000) === 0100000;
    }
}

/** @return array<string, string> */
function productionSiteRoots(): array
{
    return [
        'gulf-careers' => '/home/accountingcourse/public_html/gulf-careers',
        'online-courses' => '/home/accountingcourse/public_html/courses/online-courses',
        'tally-prime-kerala' => '/home/accountingcourse/public_html/courses/tally-prime-kerala',
    ];
}

/** @param list<string> $arguments */
function receiverModeFromEnvironment(array $arguments): array
{
    $originalCommand = getenv('SSH_ORIGINAL_COMMAND');
    if ($originalCommand !== false) {
        return match ($originalCommand) {
            'deploy' => [$arguments[1] ?? '', 'deploy'],
            'deploy --dry-run' => [$arguments[1] ?? '', 'dry-run'],
            'probe' => [$arguments[1] ?? '', 'probe'],
            default => throw new RuntimeException('SSH command is not allowed.'),
        };
    }

    $site = $arguments[1] ?? '';
    $option = $arguments[2] ?? '';
    if (count($arguments) > 3) {
        throw new RuntimeException('Usage: php receive.php <site> [--dry-run|--probe].');
    }
    $mode = match ($option) {
        '' => 'deploy',
        '--dry-run' => 'dry-run',
        '--probe' => 'probe',
        default => throw new RuntimeException('Usage: php receive.php <site> [--dry-run|--probe].'),
    };
    return [$site, $mode];
}

if (!defined('LANDING_DEPLOY_RECEIVER_NO_MAIN')) {
    try {
        [$site, $mode] = receiverModeFromEnvironment($argv);
        $receiver = new DeployReceiver(
            productionSiteRoots(),
            '/home/accountingcourse/landing-deploy/backups',
            '/home/accountingcourse/landing-deploy/deploy.lock'
        );
        $result = $receiver->run($site, $mode);
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
}
