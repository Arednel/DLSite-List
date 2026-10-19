<?php

namespace Tests\Unit\Docker;

use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[RequiresOperatingSystemFamily('Linux')]
class DockerRuntimeEnvironmentTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir() . '/dlsite-list-runtime-env-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory . '/secrets', 0777, true);
        mkdir($this->temporaryDirectory . '/mysql', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_initializer_publishes_laravel_environment_from_saved_secrets(): void
    {
        file_put_contents(
            $this->environmentSource(),
            "APP_NAME=\"DLSite List\"\nAPP_KEY=\nDB_PASSWORD=\nAPP_DEBUG=true\n"
        );

        $appKey = 'base64:' . base64_encode(str_repeat('a', 32));
        $databasePassword = 'password-$-"-\\-value';

        file_put_contents($this->secretsDirectory() . '/app_key', $appKey);
        file_put_contents($this->secretsDirectory() . '/db_password', $databasePassword);

        $process = $this->runInitializer();
        $process->mustRun();

        $contents = file_get_contents($this->secretsDirectory() . '/laravel.env');

        $this->assertNotFalse($contents);

        $environment = Dotenv::parse($contents);

        $this->assertSame('DLSite List', $environment['APP_NAME']);
        $this->assertSame('true', $environment['APP_DEBUG']);
        $this->assertSame($appKey, $environment['APP_KEY']);
        $this->assertSame($databasePassword, $environment['DB_PASSWORD']);
    }

    public function test_missing_environment_source_is_refused(): void
    {
        file_put_contents(
            $this->secretsDirectory() . '/app_key',
            'base64:' . base64_encode(str_repeat('b', 32))
        );
        file_put_contents($this->secretsDirectory() . '/db_password', 'saved-password');

        $process = $this->runInitializer();
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Docker environment file is missing or unreadable', $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->secretsDirectory() . '/laravel.env');
    }

    private function runInitializer(): Process
    {
        return new Process(
            [
                PHP_BINARY,
                'docker/init-secrets.php',
                $this->secretsDirectory(),
                $this->temporaryDirectory . '/mysql',
                $this->environmentSource(),
            ],
            dirname(__DIR__, 3),
            [
                'APP_KEY' => '',
                'DB_PASSWORD' => '',
            ],
            null,
            30
        );
    }

    private function secretsDirectory(): string
    {
        return $this->temporaryDirectory . '/secrets';
    }

    private function environmentSource(): string
    {
        return $this->temporaryDirectory . '/.env.docker';
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
