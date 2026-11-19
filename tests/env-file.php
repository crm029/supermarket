<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$path = tempnam(sys_get_temp_dir(), 'sm-env-');
if (!is_string($path)) throw new RuntimeException('Unable to create temporary environment fixture.');
try {
    file_put_contents($path, implode("\n", [
        '# ignored comment',
        'SM_ENV_TEST_VALUE="quoted sandbox value"',
        'SM_ENV_PROCESS_PRECEDENCE=file-value',
        'INVALID-KEY=ignored',
        'SM_ENV_EMPTY=',
    ]));
    putenv('SM_ENV_PROCESS_PRECEDENCE=process-value');
    app_load_env_file($path);

    if (app_env('SM_ENV_TEST_VALUE') !== 'quoted sandbox value') {
        throw new RuntimeException('Quoted .env values were not loaded.');
    }
    if (app_env('SM_ENV_PROCESS_PRECEDENCE') !== 'process-value') {
        throw new RuntimeException('The .env loader overwrote a process-level environment value.');
    }
    if (app_env('SM_ENV_EMPTY', 'fallback') !== 'fallback') {
        throw new RuntimeException('Empty .env values should use the configured fallback.');
    }
    if (app_env('INVALID-KEY') !== null) {
        throw new RuntimeException('An invalid environment key was loaded.');
    }
} finally {
    @unlink($path);
    putenv('SM_ENV_PROCESS_PRECEDENCE');
    unset($_ENV['SM_ENV_TEST_VALUE'], $_ENV['SM_ENV_EMPTY']);
}

echo "Environment file checks passed.\n";
