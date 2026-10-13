<?php
// Offline regression checks. Uses temporary, fictional media and a random key.
require_once $argv[1] ?? dirname(__DIR__) . '/scripts/atelier-state.php';
putenv('ATELIER_STATE_KEY=' . base64_encode(random_bytes(32)));
$root = sys_get_temp_dir() . '/atelier-recovery-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
putenv('ATELIER_STATE_UPLOADS_DIR=' . $root);
$failures = 0;
function check(string $name, callable $test): void {
    global $failures;
    try { $test(); echo "PASS: $name\n"; }
    catch (Throwable $e) { $failures++; echo "FAIL: $name — " . $e->getMessage() . "\n"; }
}
function expect(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
function call_private(string $name, ...$args) {
    return (new ReflectionMethod(Atelier_State::class, $name))->invoke(null, ...$args);
}
function media_payload(string $payload): string {
    return media_compressed(gzencode("ATELIERMEDIA1\n" . $payload));
}
function media_compressed(string $compressed): string {
    $magic = "ATELIERMEDIA1\n"; $iv = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt($compressed, 'aes-256-gcm', base64_decode(getenv('ATELIER_STATE_KEY')), OPENSSL_RAW_DATA, $iv, $tag, 'atelier.wordpress.media.v1');
    return $magic . $iv . $tag . $cipher;
}
function entry(string $name, string $bytes): string {
    return pack('N', strlen($name)) . pack('J', strlen($bytes)) . $name . $bytes;
}
try {
    mkdir($root . '/2026');
    file_put_contents($root . '/2026/new-upload.webp', 'fictional uploaded bytes');
    $snapshot = call_private('mediaArchive');
    check('new upload survives recovery byte for byte', function () use ($root, $snapshot) {
        unlink($root . '/2026/new-upload.webp');
        call_private('restoreMedia', $snapshot);
        expect(file_get_contents($root . '/2026/new-upload.webp') === 'fictional uploaded bytes', 'uploaded file was not restored');
    });
    check('deleted uploads stay deleted after synchronizing an older instance', function () use ($root, $snapshot) {
        file_put_contents($root . '/stale-file.webp', 'deleted in latest snapshot');
        call_private('restoreMedia', $snapshot);
        expect(!file_exists($root . '/stale-file.webp'), 'stale upload survives authoritative restore');
    });
    check('invalid manifest cannot partially overwrite an existing upload', function () use ($root) {
        $bad = media_payload(entry('2026/new-upload.webp', 'wrong replacement') . entry('../outside', 'unsafe'));
        try { call_private('restoreMedia', $bad); throw new LogicException('unsafe manifest accepted'); }
        catch (RuntimeException $expected) {}
        expect(file_get_contents($root . '/2026/new-upload.webp') === 'fictional uploaded bytes', 'valid prefix was written before rejecting invalid suffix');
    });
    check('duplicate manifest paths are rejected', function () {
        try { call_private('restoreMedia', media_payload(entry('duplicate', 'a') . entry('duplicate', 'b'))); }
        catch (RuntimeException $expected) { return; }
        throw new LogicException('duplicate path accepted');
    });
    check('file and directory path conflicts are rejected before writes', function () {
        try { call_private('restoreMedia', media_payload(entry('conflict', 'a') . entry('conflict/child', 'b'))); }
        catch (RuntimeException $expected) { return; }
        throw new LogicException('file/directory conflict accepted');
    });
    check('existing symbolic links prevent recovery without writing through them', function () use ($root, $snapshot) {
        symlink('/tmp', $root . '/outside-link');
        try {
            try { call_private('restoreMedia', $snapshot); }
            catch (RuntimeException $expected) { return; }
            throw new LogicException('symbolic link accepted');
        } finally { unlink($root . '/outside-link'); }
    });
    check('modified ciphertext is rejected', function () use ($snapshot) {
        $bad = $snapshot; $bad[strlen($bad) - 1] = chr(ord($bad[strlen($bad) - 1]) ^ 1);
        try { call_private('restoreMedia', $bad); }
        catch (RuntimeException $expected) { return; }
        throw new LogicException('modified ciphertext accepted');
    });
    check('authenticated truncated gzip is rejected', function () use ($root) {
        $gzip = gzencode("ATELIERMEDIA1\n" . entry('small', 'safe'));
        try { call_private('restoreMedia', media_compressed(substr($gzip, 0, -4))); }
        catch (RuntimeException $expected) {
            expect(file_get_contents($root . '/2026/new-upload.webp') === 'fictional uploaded bytes' && !file_exists($root . '/small'), 'rejected gzip changed uploads');
            return;
        }
        throw new LogicException('truncated gzip accepted');
    });
    check('authenticated trailing gzip bytes are rejected', function () use ($root) {
        $gzip = gzencode("ATELIERMEDIA1\n" . entry('small', 'safe'));
        try { call_private('restoreMedia', media_compressed($gzip . 'trailing')); }
        catch (RuntimeException $expected) {
            expect(file_get_contents($root . '/2026/new-upload.webp') === 'fictional uploaded bytes' && !file_exists($root . '/small'), 'rejected gzip changed uploads');
            return;
        }
        throw new LogicException('trailing compressed bytes accepted');
    });
    check('authenticated gzip expansion beyond capacity is rejected', function () use ($root) {
        $deflate = deflate_init(ZLIB_ENCODING_GZIP);
        $gzip = deflate_add($deflate, "ATELIERMEDIA1\n", ZLIB_NO_FLUSH);
        for ($i = 0; $i < 65; $i++) $gzip .= deflate_add($deflate, str_repeat('x', 1048576), ZLIB_NO_FLUSH);
        $gzip .= deflate_add($deflate, '', ZLIB_FINISH);
        try { call_private('restoreMedia', media_compressed($gzip)); }
        catch (RuntimeException $expected) {
            expect(file_get_contents($root . '/2026/new-upload.webp') === 'fictional uploaded bytes' && !file_exists($root . '/small'), 'rejected gzip changed uploads');
            return;
        }
        throw new LogicException('oversized expanded payload accepted');
    });
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
exit($failures ? 1 : 0);
