<?php

$secretsDirectory = $argv[1] ?? '/run/dlsite-secrets';
$mysqlDataDirectory = $argv[2] ?? '/var/lib/mysql-existing';
$environmentSource = $argv[3] ?? '/var/www/dlsite_list/docker/.env.docker';

function environmentValue(string $name): ?string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        return null;
    }

    if (str_contains($value, "\r") || str_contains($value, "\n")) {
        throw new RuntimeException("Environment value {$name} contains a line break.");
    }

    return $value;
}

function savedSecret(string $path): ?string
{
    if (! file_exists($path)) {
        return null;
    }

    if (! is_readable($path)) {
        throw new RuntimeException("Unable to read secret: {$path}");
    }

    $value = file_get_contents($path);

    if ($value === false) {
        throw new RuntimeException("Unable to read secret: {$path}");
    }

    $value = rtrim($value, "\r\n");

    if ($value === '') {
        throw new RuntimeException("Saved secret is empty: {$path}");
    }

    if (str_contains($value, "\r") || str_contains($value, "\n")) {
        throw new RuntimeException("Saved secret contains a line break: {$path}");
    }

    return $value;
}

function saveFile(string $path, string $value): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        throw new RuntimeException("Unable to create directory: {$directory}");
    }

    $temporaryPath = tempnam($directory, '.secret-');

    if ($temporaryPath === false) {
        throw new RuntimeException("Unable to create temporary file in {$directory}");
    }

    try {
        if (file_put_contents($temporaryPath, $value, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write file: {$path}");
        }

        if (! chmod($temporaryPath, 0444)) {
            throw new RuntimeException("Unable to set permissions for file: {$path}");
        }

        if (! rename($temporaryPath, $path)) {
            throw new RuntimeException("Unable to publish file: {$path}");
        }
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
    }
}

function generateAppKey(): string
{
    # Let Laravel generate a secure key for the configured cipher; capture it without logging it.
    $process = proc_open(
        [PHP_BINARY, 'artisan', 'key:generate', '--show', '--no-ansi', '--no-interaction'],
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        dirname(__DIR__)
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start Laravel application key generation.');
    }

    $output = stream_get_contents($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException(trim($errorOutput) ?: 'Laravel application key generation failed.');
    }

    $key = trim($output);

    if ($key === '') {
        throw new RuntimeException('Laravel did not generate an application key.');
    }

    return $key;
}

function resolveAppKey(string $path): string
{
    $value = environmentValue('APP_KEY');

    if ($value !== null) {
        saveFile($path, $value);
        echo "APP_KEY: saved supplied value.\n";

        return $value;
    }

    $value = savedSecret($path);

    if ($value !== null) {
        echo "APP_KEY: using saved value.\n";

        return $value;
    }

    $value = generateAppKey();
    saveFile($path, $value);
    echo "APP_KEY: generated and saved.\n";

    return $value;
}

function resolveDatabasePassword(string $path, string $mysqlDataDirectory): string
{
    $value = environmentValue('DB_PASSWORD');

    if ($value !== null) {
        saveFile($path, $value);
        echo "DB_PASSWORD: saved supplied value.\n";

        return $value;
    }

    $value = savedSecret($path);

    if ($value !== null) {
        echo "DB_PASSWORD: using saved value.\n";

        return $value;
    }

    if (is_dir($mysqlDataDirectory.'/mysql')) {
        throw new RuntimeException(
            "Existing MySQL data was detected, but no database password is available.\n".
            "Set DB_PASSWORD in docker/.env.docker to the password currently used by this installation, then start Docker again."
        );
    }

    # Generate 32 cryptographically random bytes and encode them as a 64-character hexadecimal password.
    $value = bin2hex(random_bytes(32));
    saveFile($path, $value);
    echo "DB_PASSWORD: generated and saved.\n";

    return $value;
}

function encodeEnvironmentValue(string $value): string
{
    return '"'.str_replace(
        ['\\', '"', '$'],
        ['\\\\', '\\"', '\\$'],
        $value
    ).'"';
}

function setEnvironmentValue(string $contents, string $name, string $value): string
{
    $line = $name.'='.encodeEnvironmentValue($value);
    $pattern = '/^(?:export[ \t]+)?'.preg_quote($name, '/').'[ \t]*=.*$/m';

    if (preg_match($pattern, $contents) === 1) {
        return preg_replace_callback($pattern, static fn (): string => $line, $contents, 1) ?? $contents;
    }

    if ($contents !== '' && ! str_ends_with($contents, "\n")) {
        $contents .= "\n";
    }

    return $contents.$line."\n";
}

function publishLaravelEnvironment(
    string $sourcePath,
    string $targetPath,
    string $appKey,
    string $databasePassword
): void {
    if (! is_readable($sourcePath)) {
        throw new RuntimeException("Docker environment file is missing or unreadable: {$sourcePath}");
    }

    $contents = file_get_contents($sourcePath);

    if ($contents === false) {
        throw new RuntimeException("Unable to read Docker environment file: {$sourcePath}");
    }

    $contents = setEnvironmentValue($contents, 'APP_KEY', $appKey);
    $contents = setEnvironmentValue($contents, 'DB_PASSWORD', $databasePassword);

    saveFile($targetPath, $contents);
}

try {
    $appKey = resolveAppKey($secretsDirectory.'/app_key');
    $databasePassword = resolveDatabasePassword($secretsDirectory.'/db_password', $mysqlDataDirectory);

    publishLaravelEnvironment(
        $environmentSource,
        $secretsDirectory.'/laravel.env',
        $appKey,
        $databasePassword
    );

    echo "Laravel environment: published.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
