<?php
// Exercise large save/restore media phases under PHP's default 128 MiB limit.
require_once $argv[1] ?? dirname(__DIR__) . '/scripts/atelier-state.php';
putenv('ATELIER_STATE_KEY=' . base64_encode(random_bytes(32)));
$root = sys_get_temp_dir() . '/atelier-memory-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
putenv('ATELIER_STATE_UPLOADS_DIR=' . $root);
$mode = $argv[2] ?? 'restore';
$payloadPath = null;
try {
    $chunk = str_repeat('x', 2 * 1024 * 1024);
    for ($i = 0; $i < 30; $i++) file_put_contents($root . '/' . $i . '.bin', $chunk);
    unset($chunk);
    $mediaArchive = new ReflectionMethod(Atelier_State::class, 'mediaArchive');
    if ($mode === 'save') {
        memory_reset_peak_usage();
        $saveArchive = $mediaArchive->invoke(null);
        $savePeak = round(memory_get_peak_usage(true) / 1048576, 1);
        if ($savePeak >= 128) throw new RuntimeException('Media snapshot exceeded PHP 128 MiB budget.');
        echo 'PASS: 60 MiB media snapshot fits memory limit; peak ' . $savePeak . " MiB.\n";
    } else {
        if ($mode !== 'restore') throw new InvalidArgumentException('Mode must be save or restore.');
        $archive = $mediaArchive->invoke(null);
        foreach (glob($root . '/*') as $path) unlink($path);
        memory_reset_peak_usage();
        // Match sync(): stage media before SQL is decrypted or allocated.
        $mediaFiles = new ReflectionMethod(Atelier_State::class, 'mediaFiles');
        $stageMedia = new ReflectionMethod(Atelier_State::class, 'stageMedia');
        $removeTree = new ReflectionMethod(Atelier_State::class, 'removeTree');
        $files = $mediaFiles->invoke(null, $archive);
        $payloadPath = $files['payloadPath'];
        $staged = $stageMedia->invoke(null, $files);
        unlink($payloadPath); $payloadPath = null;
        unset($files, $archive);
        $sql = str_repeat('s', 64 * 1024 * 1024);
        if (!is_string($sql)) throw new RuntimeException('SQL test state unavailable.');
        if (round(memory_get_peak_usage(true) / 1048576, 1) >= 128) throw new RuntimeException('Combined recovery state exceeded PHP 128 MiB budget.');
        echo 'PASS: media staged before 64 MiB SQL allocation; peak ' . round(memory_get_peak_usage(true) / 1048576, 1) . " MiB.\n";
        unset($sql);
        $removeTree->invoke(null, $staged['staging']);
    }
} finally {
    if (is_string($payloadPath)) @unlink($payloadPath);
    foreach (glob($root . '/*') as $path) unlink($path);
    rmdir($root);
}
