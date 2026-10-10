<?php
// Standalone recovery helper. Never prints credentials, SQL or customer data.
final class Atelier_State {
    private static $lock;
    private static ?string $fetched = null;
    private const MAGIC = "ATELIERSTATE1\n";
    private const AAD = 'atelier.wordpress.database.v1';
    private const MEDIA_MAGIC = "ATELIERMEDIA1\n";
    private const MEDIA_AAD = 'atelier.wordpress.media.v1';
    private const MEDIA_MAX = 20 * 1024 * 1024;
    private const MEDIA_EXPANDED_MAX = 64 * 1024 * 1024;

    public static function enabled(): bool { return getenv('ATELIER_STATE_REPO') !== false && getenv('ATELIER_STATE_REPO') !== ''; }
    private static function dir(): string { return getenv('ATELIER_STATE_DIR') ?: '/var/lib/atelier/state'; }
    private static function branch(): string {
        $branch = getenv('ATELIER_STATE_BRANCH') ?: 'main';
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $branch)) throw new RuntimeException('Invalid recovery branch.');
        return $branch;
    }
    public static function lock(): void {
        if (is_resource(self::$lock)) return;
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Recovery storage is unavailable.');
        chmod($dir, 0700);
        self::$lock = fopen($dir . '/request.lock', 'c');
        if (!self::$lock) throw new RuntimeException('Recovery lock is unavailable.');
        $deadline = microtime(true) + 45;
        while (!flock(self::$lock, LOCK_EX | LOCK_NB)) {
            if (microtime(true) > $deadline) throw new RuntimeException('Recovery storage is busy.');
            usleep(100000);
        }
    }
    private static function run(array $command, ?string $input = null, ?string $cwd = null): string {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($process)) throw new RuntimeException('Recovery operation could not start.');
        if ($input !== null) {
            $offset = 0;
            while ($offset < strlen($input)) {
                $written = fwrite($pipes[0], substr($input, $offset, 65536));
                if ($written === false || $written === 0) { fclose($pipes[0]); proc_terminate($process); throw new RuntimeException('Recovery input failed.'); }
                $offset += $written;
            }
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]); // Discard diagnostics: SQL errors can contain personal data.
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Recovery operation failed.');
        return trim($output);
    }
    private static function key(): string {
        $key = base64_decode(getenv('ATELIER_STATE_KEY') ?: '', true);
        if ($key === false || strlen($key) !== 32) throw new RuntimeException('Recovery encryption key is unavailable.');
        return $key;
    }
    private static function prepare(): void {
        self::key();
        $dir = self::dir();
        $repo = getenv('ATELIER_STATE_REPO') ?: '';
        if (!preg_match('~^ssh://git@ssh\.github\.com:443/[a-zA-Z0-9_-]+/[a-zA-Z0-9_.-]+\.git$~', $repo)) throw new RuntimeException('Invalid recovery repository.');
        $ssh = base64_decode(getenv('ATELIER_STATE_SSH_KEY_B64') ?: '', true);
        if (!$ssh || !str_contains($ssh, 'BEGIN OPENSSH PRIVATE KEY')) throw new RuntimeException('Recovery repository key is unavailable.');
        file_put_contents($dir . '/identity', $ssh, LOCK_EX); chmod($dir . '/identity', 0600);
        $known_hosts = getenv('ATELIER_STATE_KNOWN_HOSTS') ?: '/usr/local/lib/atelier-github-known-hosts';
        putenv('GIT_SSH_COMMAND=ssh -i ' . escapeshellarg($dir . '/identity') . ' -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=' . escapeshellarg($known_hosts) . ' -o ControlMaster=auto -o ControlPersist=60 -o ControlPath=' . escapeshellarg($dir . '/ssh-control') . ' -o ConnectTimeout=10 -o ServerAliveInterval=5 -o ServerAliveCountMax=2');
        if (!is_dir($dir . '/repo/.git')) {
            mkdir($dir . '/repo', 0700, true);
            self::run(['git', 'init', '-b', self::branch()], null, $dir . '/repo');
            self::git(['remote', 'add', 'origin', $repo]);
        }
        self::git(['config', 'user.name', 'Atelier recovery']);
        self::git(['config', 'user.email', 'atelier-recovery@users.noreply.github.com']);
    }
    private static function git(array $args): string {
        return self::run(array_merge(['git', '-c', 'safe.directory=' . self::dir() . '/repo'], $args), null, self::dir() . '/repo');
    }
    private static function remote(): string {
        if (self::$fetched === null) {
            self::git(['fetch', '--depth=1', 'origin', self::branch()]);
            self::$fetched = self::git(['rev-parse', 'FETCH_HEAD']);
        }
        return self::$fetched;
    }
    private static function mysqlOptions(): string {
        $values = [
            'host' => getenv('WORDPRESS_DB_HOST') ?: '127.0.0.1',
            'user' => getenv('WORDPRESS_DB_USER') ?: 'atelier',
            'password' => getenv('WORDPRESS_DB_PASSWORD') ?: '',
            'default-character-set' => 'utf8mb4',
        ];
        $content = "[client]\n";
        foreach ($values as $key => $value) $content .= $key . '="' . str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], $value) . '"' . "\n";
        $path = self::dir() . '/mysql.cnf';
        file_put_contents($path, $content, LOCK_EX); chmod($path, 0600);
        return $path;
    }
    private static function database(): string {
        $name = getenv('WORDPRESS_DB_NAME') ?: 'atelier';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new RuntimeException('Invalid recovery database name.');
        return $name;
    }
    public static function encrypt(string $sql): string {
        if (strlen($sql) > 64 * 1024 * 1024) throw new RuntimeException('Demo recovery capacity exceeded.');
        $iv = random_bytes(12); $tag = '';
        $ciphertext = openssl_encrypt(gzencode($sql, 6), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::AAD);
        if ($ciphertext === false) throw new RuntimeException('Recovery encryption failed.');
        $data = self::MAGIC . $iv . $tag . $ciphertext;
        if (strlen($data) > 5 * 1024 * 1024) throw new RuntimeException('Demo recovery capacity exceeded.');
        return $data;
    }
    private static function decrypt(string $data): string {
        if (!str_starts_with($data, self::MAGIC) || strlen($data) > 5 * 1024 * 1024) throw new RuntimeException('Invalid recovery file.');
        $body = substr($data, strlen(self::MAGIC));
        $compressed = openssl_decrypt(substr($body, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($body, 0, 12), substr($body, 12, 16), self::AAD);
        if ($compressed === false) throw new RuntimeException('Recovery authentication failed.');
        $sql = gzdecode($compressed, 64 * 1024 * 1024);
        if ($sql === false) throw new RuntimeException('Recovery decompression failed.');
        return $sql;
    }
    private static function uploadsDir(): string { return getenv('ATELIER_STATE_UPLOADS_DIR') ?: '/var/lib/atelier/uploads'; }
    private static function mediaArchive(): string {
        $root = self::uploadsDir();
        if (!is_dir($root) && !mkdir($root, 0755, true)) throw new RuntimeException('Media recovery directory is unavailable.');
        $rootReal = realpath($root);
        if ($rootReal === false) throw new RuntimeException('Media recovery directory is unavailable.');
        $files = []; $total = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isLink()) throw new RuntimeException('Media recovery does not allow symbolic links.');
            if (!$entry->isFile()) continue;
            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($rootReal) + 1));
            if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(^/|(^|/)\.\.?(/|$))~', $path)) throw new RuntimeException('Invalid media path.');
            $size = $entry->getSize(); $total += $size;
            if ($size > self::MEDIA_EXPANDED_MAX || $total > self::MEDIA_EXPANDED_MAX || count($files) >= 2000) throw new RuntimeException('Demo media recovery capacity exceeded.');
            $files[$path] = $entry->getPathname();
        }
        ksort($files, SORT_STRING);
        $payload = self::MEDIA_MAGIC;
        foreach ($files as $relative => $absolute) {
            $name = strlen($relative); $bytes = filesize($absolute);
            if ($name > 1024 || $bytes === false) throw new RuntimeException('Invalid media file.');
            if ($total + (count($files) * 12) + strlen($relative) > self::MEDIA_EXPANDED_MAX) throw new RuntimeException('Demo media recovery capacity exceeded.');
            $payload .= pack('N', $name) . pack('J', $bytes) . $relative;
            $stream = fopen($absolute, 'rb');
            if (!$stream) throw new RuntimeException('Media file could not be read.');
            while (!feof($stream)) { $chunk = fread($stream, 65536); if ($chunk === false) { fclose($stream); throw new RuntimeException('Media file could not be read.'); } $payload .= $chunk; }
            fclose($stream);
        }
        $compressed = gzencode($payload, 6); unset($payload);
        if ($compressed === false) throw new RuntimeException('Media compression failed.');
        $iv = random_bytes(12); $tag = '';
        $ciphertext = openssl_encrypt($compressed, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::MEDIA_AAD);
        unset($compressed);
        if ($ciphertext === false) throw new RuntimeException('Media encryption failed.');
        $data = self::MEDIA_MAGIC . $iv . $tag . $ciphertext;
        if (strlen($data) > self::MEDIA_MAX) throw new RuntimeException('Demo media recovery capacity exceeded.');
        return $data;
    }
    private static function restoreMedia(string $data): void {
        if (!str_starts_with($data, self::MEDIA_MAGIC) || strlen($data) > self::MEDIA_MAX) throw new RuntimeException('Invalid media recovery file.');
        $offset = strlen(self::MEDIA_MAGIC); $body = substr($data, $offset);
        $compressed = openssl_decrypt(substr($body, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($body, 0, 12), substr($body, 12, 16), self::MEDIA_AAD);
        if ($compressed === false) throw new RuntimeException('Media recovery authentication failed.');
        $payload = gzdecode($compressed, self::MEDIA_EXPANDED_MAX); unset($compressed);
        if ($payload === false || !str_starts_with($payload, self::MEDIA_MAGIC)) throw new RuntimeException('Media recovery decompression failed.');
        $position = strlen(self::MEDIA_MAGIC); $root = self::uploadsDir();
        if (!is_dir($root) && !mkdir($root, 0755, true)) throw new RuntimeException('Media recovery directory is unavailable.');
        $root = realpath($root); if ($root === false) throw new RuntimeException('Media recovery directory is unavailable.');
        $count = 0;
        while ($position < strlen($payload)) {
            if (++$count > 2000 || $position + 12 > strlen($payload)) throw new RuntimeException('Invalid media manifest.');
            $nameLength = unpack('N', substr($payload, $position, 4))[1]; $size = unpack('J', substr($payload, $position + 4, 8))[1]; $position += 12;
            if ($nameLength < 1 || $nameLength > 1024 || $size > self::MEDIA_EXPANDED_MAX || $position + $nameLength + $size > strlen($payload)) throw new RuntimeException('Invalid media manifest.');
            $relative = substr($payload, $position, $nameLength); $position += $nameLength;
            if (str_contains($relative, "\0") || str_contains($relative, '\\') || str_starts_with($relative, '/') || preg_match('~(^|/)\.\.?(/|$)~', $relative)) throw new RuntimeException('Unsafe media path.');
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $parent = dirname($target); if (!is_dir($parent) && !mkdir($parent, 0755, true)) throw new RuntimeException('Media directory could not be restored.');
            $parentReal = realpath($parent);
            if ($parentReal === false || !str_starts_with($parentReal . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) throw new RuntimeException('Unsafe media path.');
            $temporary = tempnam($parentReal, '.atelier-');
            if ($temporary === false || file_put_contents($temporary, substr($payload, $position, $size), LOCK_EX) !== $size || !rename($temporary, $target)) { if ($temporary) @unlink($temporary); throw new RuntimeException('Media file could not be restored.'); }
            chmod($target, 0644); $position += $size;
        }
        unset($payload);
    }
    public static function sync(): void {
        self::lock(); self::prepare();
        $remote = self::remote();
        $applied = @file_get_contents(self::dir() . '/applied');
        $head = '';
        try { $head = self::git(['rev-parse', 'HEAD']); } catch (RuntimeException $error) {}
        if ($head !== $remote || $applied !== $remote) {
            self::git(['reset', '--hard', $remote]);
            $path = self::dir() . '/repo/state.enc';
            if (!is_file($path)) throw new RuntimeException('Recovery snapshot is missing.');
            $sql = self::decrypt(file_get_contents($path));
            self::run(['mariadb', '--defaults-extra-file=' . self::mysqlOptions(), self::database()], $sql);
            $media = self::dir() . '/repo/media.enc';
            if (is_file($media)) self::restoreMedia(file_get_contents($media));
            file_put_contents(self::dir() . '/applied', $remote, LOCK_EX);
        }
    }
    public static function save(): void {
        self::lock(); self::prepare();
        $head = self::git(['rev-parse', 'HEAD']);
        if ($head !== self::remote()) throw new RuntimeException('A newer recovery snapshot exists. Retry the request.');
        $temporary = tempnam(self::dir(), 'dump-'); chmod($temporary, 0600);
        try {
            self::run(['mariadb-dump', '--defaults-extra-file=' . self::mysqlOptions(), '--single-transaction', '--skip-comments', '--hex-blob', '--result-file=' . $temporary, self::database()]);
            $sql = file_get_contents($temporary);
            if (!$sql || !str_contains($sql, 'CREATE TABLE')) throw new RuntimeException('Recovery database export is empty.');
            $data = self::encrypt($sql);
            file_put_contents(self::dir() . '/repo/state.enc', $data, LOCK_EX);
            $media = self::mediaArchive();
            file_put_contents(self::dir() . '/repo/media.enc', $media, LOCK_EX);
            self::git(['add', 'state.enc', 'media.enc']);
            self::git(['commit', '-m', 'Save encrypted Atelier state ' . gmdate('Y-m-d H:i:s') . ' UTC']);
            self::git(['fetch', '--deepen=10', 'origin', self::branch()]);
            $depth = (int)self::git(['rev-list', '--count', 'FETCH_HEAD']);
            if ($depth >= 11) {
                $expected = self::remote();
                self::git(['checkout', '--orphan', 'atelier-retention-' . gmdate('YmdHis')]);
                self::git(['add', '-f', 'state.enc', 'media.enc']);
                self::git(['commit', '-m', 'Encrypted Atelier recovery snapshot ' . gmdate('Y-m-d H:i:s') . ' UTC']);
                self::git(['push', '--force-with-lease=refs/heads/' . self::branch() . ':' . $expected, 'origin', 'HEAD:' . self::branch()]);
            } else {
                self::git(['push', 'origin', 'HEAD:' . self::branch()]);
            }
            $saved = self::git(['rev-parse', 'HEAD']);
            self::$fetched = $saved;
            file_put_contents(self::dir() . '/applied', $saved, LOCK_EX);
            file_put_contents(self::dir() . '/status.json', json_encode(['saved_at' => gmdate('c'), 'database_bytes' => strlen($data), 'media_bytes' => strlen($media)]), LOCK_EX);
        } finally { @unlink($temporary); }
    }
    public static function status(): array {
        return json_decode(@file_get_contents(self::dir() . '/status.json') ?: '{}', true) ?: [];
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if (!Atelier_State::enabled()) throw new RuntimeException('Recovery is not configured.');
        if (($argv[1] ?? '') === 'restore') Atelier_State::sync();
        elseif (($argv[1] ?? '') === 'save') Atelier_State::save();
        else throw new RuntimeException('Unknown recovery operation.');
        echo "Atelier recovery operation completed.\n";
    } catch (Throwable $error) { fwrite(STDERR, "Atelier recovery operation failed. No database contents were printed.\n"); exit(1); }
}
