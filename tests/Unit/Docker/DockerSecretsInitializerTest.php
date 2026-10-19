<?php

namespace Tests\Unit\Docker;

use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[RequiresOperatingSystemFamily('Linux')]
class DockerSecretsInitializerTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir() . '/dlsite-list-secrets-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0777, true);
        file_put_contents($this->environmentSource(), "APP_KEY=\nDB_PASSWORD=\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_missing_secrets_are_generated_once_and_reused(): void
    {
        $firstRun = $this->runInitializer();

        $firstRun->mustRun();

        $appKey = $this->readSecret('app_key');
        $databasePassword = $this->readSecret('db_password');

        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/=]+$/', $appKey);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $databasePassword);
        $this->assertStringContainsString('APP_KEY: generated and saved.', $firstRun->getOutput());
        $this->assertStringContainsString('DB_PASSWORD: generated and saved.', $firstRun->getOutput());

        $secondRun = $this->runInitializer();

        $secondRun->mustRun();

        $this->assertSame($appKey, $this->readSecret('app_key'));
        $this->assertSame($databasePassword, $this->readSecret('db_password'));
        $this->assertStringContainsString('APP_KEY: using saved value.', $secondRun->getOutput());
        $this->assertStringContainsString('DB_PASSWORD: using saved value.', $secondRun->getOutput());
    }

    public function test_environment_values_replace_saved_values(): void
    {
        $this->writeSecret('app_key', 'saved-app-key');
        $this->writeSecret('db_password', 'saved-db-password');
        mkdir($this->mysqlDataDirectory() . '/mysql', 0777, true);

        $appKey = 'base64:' . base64_encode(str_repeat('a', 32));
        $databasePassword = 'environment-password';

        $process = $this->runInitializer([
            'APP_KEY' => $appKey,
            'DB_PASSWORD' => $databasePassword,
        ]);

        $process->mustRun();

        $this->assertSame($appKey, $this->readSecret('app_key'));
        $this->assertSame($databasePassword, $this->readSecret('db_password'));
    }

    public function test_existing_database_without_a_password_source_is_refused(): void
    {
        mkdir($this->mysqlDataDirectory() . '/mysql', 0777, true);

        $process = $this->runInitializer([
            'APP_KEY' => 'base64:' . base64_encode(str_repeat('c', 32)),
        ]);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertFileDoesNotExist($this->secretsDirectory() . '/db_password');
        $this->assertStringContainsString(
            'Existing MySQL data was detected, but no database password is available.',
            $process->getErrorOutput()
        );
        $this->assertStringContainsString('Set DB_PASSWORD in docker/.env.docker', $process->getErrorOutput());
    }

    private function runInitializer(array $environment = []): Process
    {
        $environment = array_merge([
            'APP_KEY' => '',
            'DB_PASSWORD' => '',
        ], $environment);

        return new Process(
            [
                PHP_BINARY,
                'docker/init-secrets.php',
                $this->secretsDirectory(),
                $this->mysqlDataDirectory(),
                $this->environmentSource(),
            ],
            dirname(__DIR__, 3),
            $environment,
            null,
            30
        );
    }

    private function secretsDirectory(): string
    {
        return $this->temporaryDirectory . '/secrets';
    }

    private function mysqlDataDirectory(): string
    {
        return $this->temporaryDirectory . '/mysql';
    }

    private function environmentSource(): string
    {
        return $this->temporaryDirectory . '/.env.docker';
    }

    private function readSecret(string $name): string
    {
        $value = file_get_contents($this->secretsDirectory() . '/' . $name);

        $this->assertNotFalse($value);

        return $value;
    }

    private function writeSecret(string $name, string $value): void
    {
        if (! is_dir($this->secretsDirectory())) {
            mkdir($this->secretsDirectory(), 0777, true);
        }

        file_put_contents($this->secretsDirectory() . '/' . $name, $value);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
