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
    runtime = {'ATELIER_STATE_REPO': 'ssh://git@ssh.github.com:443/offline/fixture.git',
               'ATELIER_STATE_KEY': base64.b64encode(secrets.token_bytes(32)).decode(),
               'ATELIER_STATE_SSH_KEY_B64': base64.b64encode(b'-----BEGIN OPENSSH PRIVATE KEY-----\noffline-test-only\n').decode(),
               'ATELIER_STATE_DIR': '/var/lib/atelier/fixture-state',
               'ATELIER_STATE_UPLOADS_DIR': '/var/lib/atelier/uploads',
               'GIT_CONFIG_GLOBAL': '/fixture/gitconfig'}
    (fixture / 'runtime.json').write_text(json.dumps(runtime))
    (fixture / 'runtime.json').chmod(0o600)
    (fixture / 'gitconfig').write_text('[url "/fixture/remote.git"]\n\tinsteadOf = ssh://git@ssh.github.com:443/offline/fixture.git\n')
    (fixture / 'operation.php').write_text('''<?php
foreach (json_decode(file_get_contents('/fixture/runtime.json'), true) as $name => $value) putenv($name . '=' . $value);
require_once '/usr/local/lib/atelier-state.php';
try { ($argv[1] === 'save') ? Atelier_State::save() : Atelier_State::sync(); echo "Recovery operation completed.\\n"; }
catch (Throwable $e) { fwrite(STDERR, "Recovery operation refused.\\n"); exit(1); }
''')
    names = []
    try:
        def start(label, port):
            name = 'atelier-recovery-' + label + '-' + secrets.token_hex(3)
            names.append(name)
            run('docker', 'run', '-d', '--name', name, '-p', f'127.0.0.1:{port}:80',
                '-e', 'WORDPRESS_DB_PASSWORD=local-fixture-only', '-e', 'ATELIER_ADMIN_USER=fixture',
                '-e', 'ATELIER_ADMIN_PASSWORD=fictional-local-password', '-e', f'ATELIER_SITE_URL=http://127.0.0.1:{port}',
                '-v', f'{fixture}:/fixture', '-v', f'{ROOT}/tests/recovery-fixture.php:/fixture-wp.php:ro',
                '-v', f'{ROOT}/scripts/atelier-state.php:/usr/local/lib/atelier-state.php:ro', IMAGE)
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
        run('docker', 'exec', restored, 'php', '/fixture/operation.php', 'restore')
        wp(restored, 'verify')
    finally:
        for name in names:
            subprocess.run(['docker', 'rm', '-fv', name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
