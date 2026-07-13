<?php

namespace Tests\Unit\Logging;

use App\Logging\WeeklyRotatingFileHandler;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Monolog\Processor\PsrLogMessageProcessor;
use Tests\TestCase;

class LoggingConfigurationTest extends TestCase
{
    public function test_the_default_stack_uses_the_locked_weekly_log_handler(): void
    {
        $weekly = config('logging.channels.weekly');

        $this->assertSame(90, config('logging.retention_days'));
        $this->assertSame(['weekly'], config('logging.channels.stack.channels'));
        $this->assertSame('monolog', $weekly['driver'] ?? null);
        $this->assertSame(WeeklyRotatingFileHandler::class, $weekly['handler'] ?? null);
        $this->assertSame(storage_path('logs/laravel.log'), $weekly['handler_with']['filename'] ?? null);
        $this->assertSame(90, $weekly['handler_with']['retentionDays'] ?? null);
        $this->assertTrue($weekly['handler_with']['useLocking'] ?? false);
        $this->assertSame([PsrLogMessageProcessor::class], $weekly['processors'] ?? null);
    }

    public function test_laravel_can_build_and_write_with_the_weekly_channel_configuration(): void
    {
        $directory = storage_path('framework/testing/weekly-channel-' . uniqid());
        File::ensureDirectoryExists($directory);

        try {
            $configuration = config('logging.channels.weekly');
            $configuration['handler_with']['filename'] = $directory . '/laravel.log';

            Log::build($configuration)->info('Configured weekly channel entry');

            $weekStart = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify('monday this week')
                ->format('Y-m-d');
            $weeklyLog = $directory . '/laravel-' . $weekStart . '.log';

            $this->assertFileExists($weeklyLog);
            $this->assertStringContainsString('Configured weekly channel entry', File::get($weeklyLog));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
