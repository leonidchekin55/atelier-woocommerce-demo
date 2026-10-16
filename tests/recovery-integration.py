#!/usr/bin/env python3
"""Disposable Docker + real WordPress/MariaDB recovery test; no remote credentials."""
import base64
import json
import os
from pathlib import Path
import secrets
import subprocess
import tempfile
import time
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parents[1]
IMAGE = os.environ.get('ATELIER_TEST_IMAGE', 'atelier-gsc-verification:latest')

def run(*command, expected=0):
    result = subprocess.run(command, capture_output=True, text=True)
    if result.returncode != expected:
        raise RuntimeError(f'{command[0]} failed with exit {result.returncode}; diagnostics suppressed.')
    return result.stdout.strip()

with tempfile.TemporaryDirectory(prefix='atelier-offline-recovery-') as folder:
    fixture = Path(folder)
    fixture.chmod(0o755)
    runtime = {'ATELIER_STATE_REPO': 'ssh://git@ssh.github.com:443/offline/fixture.git',
               'ATELIER_STATE_KEY': base64.b64encode(secrets.token_bytes(32)).decode(),
               'ATELIER_STATE_SSH_KEY_B64': base64.b64encode(b'-----BEGIN OPENSSH PRIVATE KEY-----\noffline-test-only\n').decode(),
               'ATELIER_STATE_DIR': '/var/lib/atelier/fixture-state',
               'ATELIER_STATE_UPLOADS_DIR': '/var/lib/atelier/uploads/current',
               'GIT_CONFIG_GLOBAL': '/fixture/gitconfig'}
    (fixture / 'runtime.json').write_text(json.dumps(runtime))
    (fixture / 'runtime.json').chmod(0o644)
    (fixture / 'gitconfig').write_text('[url "/fixture/remote.git"]\n\tinsteadOf = ssh://git@ssh.github.com:443/offline/fixture.git\n')
    (fixture / 'operation.php').write_text('''<?php
foreach (json_decode(file_get_contents('/fixture/runtime.json'), true) as $name => $value) putenv($name . '=' . $value);
require_once '/usr/local/lib/atelier-state.php';
try {
    if (($argv[1] ?? '') === 'save') Atelier_State::save();
    elseif (($argv[1] ?? '') === 'bad-sql') file_put_contents('/fixture/broken/state.enc', Atelier_State::encrypt("UPDATE wp_options SET option_value='partially-imported' WHERE option_name='atelier_recovery_fixture';\nTHIS IS NOT VALID SQL;\n"));
    elseif (($argv[1] ?? '') === 'status') { echo json_encode(Atelier_State::status()); exit(0); }
    else Atelier_State::sync();
    echo "Recovery operation completed.\\n";
}
catch (Throwable $e) { fwrite(STDERR, "Recovery operation refused.\\n"); exit(1); }
''')
    names = []
    try:
        def start(label, port, data_dir=None, mail=False):
            name = 'atelier-recovery-' + label + '-' + secrets.token_hex(3)
            names.append(name)
            command = ['docker', 'run', '-d', '--name', name, '-p', f'127.0.0.1:{port}:80',
                '-e', 'WORDPRESS_DB_PASSWORD=local-fixture-only', '-e', 'ATELIER_ADMIN_USER=fixture',
                '-e', 'ATELIER_ADMIN_PASSWORD=fictional-local-password', '-e', f'ATELIER_SITE_URL=http://127.0.0.1:{port}',
                '-v', f'{fixture}:/fixture', '-v', f'{ROOT}/tests/recovery-fixture.php:/fixture-wp.php:ro',
                '-v', f'{ROOT}/scripts/atelier-state.php:/usr/local/lib/atelier-state.php:ro']
            if mail:
                command.extend(['-e', 'BREVO_API_KEY=offline-fixture-key',
                    '-e', 'BREVO_SENDER_EMAIL=atelier-sender@example.invalid',
                    '-e', 'BREVO_OWNER_EMAIL=atelier-owner@example.invalid'])
            if data_dir is not None:
                command.extend(['-v', f'{data_dir}:/var/lib/atelier'])
            run(*command, IMAGE)
            deadline = time.monotonic() + 300
            while time.monotonic() < deadline:
                try:
                    with urlopen(f'http://127.0.0.1:{port}/', timeout=30) as response:
                        if response.status == 200: return name
                except Exception: pass
                time.sleep(2)
            raise RuntimeError('Disposable WordPress did not become ready.')
        def wp(name, mode):
            print(run('docker', 'exec', name, 'wp', 'eval-file', '/fixture-wp.php', mode, '--path=/var/www/html', '--allow-root'), flush=True)
        donor = start('donor', 18087)
        run('docker', 'exec', donor, 'git', 'init', '--bare', '-b', 'main', '/fixture/remote.git')
        wp(donor, 'seed')
        run('docker', 'exec', donor, 'php', '/fixture/operation.php', 'save')
        status = json.loads(run('docker', 'exec', donor, 'php', '/fixture/operation.php', 'status'))
        if status.get('database_bytes', 0) <= 0 or status.get('media_bytes', 0) <= 0:
            raise RuntimeError('Recovery snapshot status omitted saved byte counts.')
        print('PASS: completed encrypted snapshot reports database/media byte counts.', flush=True)
        saved = run('docker', 'exec', donor, 'git', '--git-dir=/fixture/remote.git', 'rev-parse', 'main')
        run('docker', 'rm', '-fv', donor)
        names.remove(donor)
        print('Donor container and ephemeral database removed.', flush=True)
        restored = start('restored', 18088)
        # Incomplete archives must fail before importing their SQL.
        run('docker', 'exec', restored, 'git', 'clone', '/fixture/remote.git', '/fixture/broken')
        for mutation in ('missing', 'corrupt'):
            run('docker', 'exec', restored, 'git', '-C', '/fixture/broken', 'reset', '--hard', saved)
            if mutation == 'missing': run('docker', 'exec', restored, 'rm', '/fixture/broken/media.enc')
            else: run('docker', 'exec', restored, 'sh', '-c', "printf 'invalid' > /fixture/broken/media.enc")
            run('docker', 'exec', restored, 'git', '-C', '/fixture/broken', 'add', '-A')
            run('docker', 'exec', restored, 'git', '-C', '/fixture/broken', '-c', 'user.name=Offline test', '-c', 'user.email=offline@example.invalid', 'commit', '-m', mutation)
            broken = run('docker', 'exec', restored, 'git', '-C', '/fixture/broken', 'rev-parse', 'HEAD')
            run('docker', 'exec', restored, 'git', '--git-dir=/fixture/remote.git', 'fetch', '/fixture/broken', 'HEAD')
            run('docker', 'exec', restored, 'git', '--git-dir=/fixture/remote.git', 'update-ref', 'refs/heads/main', broken)
            run('docker', 'exec', restored, 'php', '/fixture/operation.php', 'restore', expected=1)
            wp(restored, 'unchanged')
            print(f'PASS: {mutation} media rejected before SQL import.', flush=True)
        run('docker', 'exec', restored, 'git', '--git-dir=/fixture/remote.git', 'update-ref', 'refs/heads/main', saved)
        run('docker', 'exec', restored, 'ln', '-s', '/tmp', '/var/lib/atelier/uploads/current/recovery-blocker')
        run('docker', 'exec', restored, 'php', '/fixture/operation.php', 'restore', expected=1)
        wp(restored, 'unchanged')
        run('docker', 'exec', restored, 'rm', '/var/lib/atelier/uploads/current/recovery-blocker')
        print('PASS: uploads symlink refused before SQL import.', flush=True)
        run('docker', 'exec', restored, 'chown', '-R', 'www-data:www-data', '/var/lib/atelier/fixture-state', '/var/lib/atelier/uploads', '/var/lib/atelier/state')
        run('docker', 'exec', restored, 'sh', '-c', "printf 'keep' > /var/lib/atelier/uploads/current/preserve-on-sql-failure")
        wp(restored, 'baseline')
        run('docker', 'exec', restored, 'git', '-C', '/fixture/broken', 'reset', '--hard', saved)
        run('docker', 'exec', restored, 'php', '/fixture/operation.php', 'bad-sql')
        run('docker', 'exec', restored, 'git', '-C', '/fixture/broken', 'add', 'state.enc')
        run('docker', 'exec', restored, 'git', '-C', '/fixture/broken', '-c', 'user.name=Offline test', '-c', 'user.email=offline@example.invalid', 'commit', '-m', 'invalid-sql')
        broken = run('docker', 'exec', restored, 'git', '-C', '/fixture/broken', 'rev-parse', 'HEAD')
        run('docker', 'exec', restored, 'git', '--git-dir=/fixture/remote.git', 'fetch', '/fixture/broken', 'HEAD')
        run('docker', 'exec', restored, 'git', '--git-dir=/fixture/remote.git', 'update-ref', 'refs/heads/main', broken)
        # On Linux, root-created Git metadata on the host bind mount is not writable by www-data.
        run('docker', 'exec', restored, 'chown', '-R', 'www-data:www-data', '/fixture/remote.git', '/fixture/broken')
        run('docker', 'exec', '--user', 'www-data', restored, 'php', '/fixture/operation.php', 'restore', expected=1)
        wp(restored, 'unchanged')
        wp(restored, 'baseline-check')
        run('docker', 'exec', restored, 'test', '-f', '/var/lib/atelier/uploads/current/preserve-on-sql-failure')
        print('PASS: partial SQL import failure rolled back the database and preserved live media.', flush=True)
        run('docker', 'exec', restored, 'git', '--git-dir=/fixture/remote.git', 'update-ref', 'refs/heads/main', saved)
        run('docker', 'exec', '--user', 'www-data', restored, 'php', '/fixture/operation.php', 'restore')
        wp(restored, 'verify')
        legacy_disk = fixture / 'legacy-disk'
        legacy_uploads = legacy_disk / 'uploads' / '2025'
        legacy_uploads.mkdir(parents=True)
        (legacy_uploads / 'kept.webp').write_bytes(b'legacy persistent upload')
        legacy = start('legacy-layout', 18089, legacy_disk)
        run('docker', 'exec', legacy, 'test', '-f', '/var/lib/atelier/uploads/current/2025/kept.webp')
        run('docker', 'exec', legacy, 'test', '-L', '/var/www/html/wp-content/uploads')
        print('PASS: legacy persistent uploads migrated to the swappable directory.', flush=True)
        interrupted_disk = fixture / 'interrupted-disk'
        previous_uploads = interrupted_disk / 'uploads' / '.atelier-previous-interrupted' / '2025'
        previous_uploads.mkdir(parents=True)
        (previous_uploads / 'kept.webp').write_bytes(b'uploads retained before interrupted swap')
        interrupted = start('interrupted-swap', 18090, interrupted_disk)
        run('docker', 'exec', interrupted, 'test', '-f', '/var/lib/atelier/uploads/current/2025/kept.webp')
        print('PASS: startup restores the previous tree after an interrupted directory swap.', flush=True)
        mailer = start('mailer', 18091, mail=True)
        wp(mailer, 'brevo-provider')
    finally:
        # Containers write Git working trees through this host bind mount as root or www-data.
        # Restore the caller's ownership before TemporaryDirectory removes it (GitHub's Linux
        # runner refuses to unlink files owned by the container user).
        owner = f'{os.getuid()}:{os.getgid()}'
        for name in names:
            subprocess.run(
                ['docker', 'exec', '--user', '0:0', name, 'chown', '-R', owner, '/fixture'],
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=False,
            )
        for name in names:
            subprocess.run(['docker', 'rm', '-fv', name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
