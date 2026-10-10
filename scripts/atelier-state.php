<?php
// Standalone recovery helper. Never prints credentials, SQL or customer data.
final class Atelier_State {
    private static $lock;
    private static ?string $fetched = null;
    private const MAGIC = "ATELIERSTATE1\n";
    private const AAD = 'atelier.wordpress.database.v1';

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
            self::git(['add', 'state.enc']);
            self::git(['commit', '-m', 'Save encrypted Atelier state ' . gmdate('Y-m-d H:i:s') . ' UTC']);
            self::git(['push', 'origin', 'HEAD:' . self::branch()]);
            $saved = self::git(['rev-parse', 'HEAD']);
            self::$fetched = $saved;
            file_put_contents(self::dir() . '/applied', $saved, LOCK_EX);
            file_put_contents(self::dir() . '/status.json', json_encode(['saved_at' => gmdate('c'), 'bytes' => strlen($data)]), LOCK_EX);
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
