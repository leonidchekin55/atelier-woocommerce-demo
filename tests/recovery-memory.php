<?php
// Restore a near-capacity archive under PHP's default 128 MiB memory budget.
require_once $argv[1] ?? dirname(__DIR__) . '/scripts/atelier-state.php';
putenv('ATELIER_STATE_KEY=' . base64_encode(random_bytes(32)));
$root = sys_get_temp_dir() . '/atelier-memory-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
putenv('ATELIER_STATE_UPLOADS_DIR=' . $root);
try {
    $chunk = str_repeat('x', 2 * 1024 * 1024);
    for ($i = 0; $i < 30; $i++) file_put_contents($root . '/' . $i . '.bin', $chunk);
    $hash = hash('sha256', $chunk);
    unset($chunk);
    $archive = (new ReflectionMethod(Atelier_State::class, 'mediaArchive'))->invoke(null);
    foreach (glob($root . '/*') as $path) unlink($path);
    memory_reset_peak_usage();
    // Simulate other WordPress/SQL allocations held during recovery.
    $otherState = str_repeat('x', 16 * 1024 * 1024);
    (new ReflectionMethod(Atelier_State::class, 'restoreMedia'))->invoke(null, $archive);
    foreach (glob($root . '/*') as $path) if (hash_file('sha256', $path) !== $hash) throw new RuntimeException('Restored media mismatch.');
    if (count(glob($root . '/*')) !== 30) throw new RuntimeException('Missing restored files.');
    echo 'PASS: near-capacity media restored; peak ' . round(memory_get_peak_usage(true) / 1048576, 1) . " MiB.\n";
} finally {
    foreach (glob($root . '/*') as $path) unlink($path);
    rmdir($root);
}
