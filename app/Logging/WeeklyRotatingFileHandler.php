<?php

namespace App\Logging;

use DateTimeImmutable;
use DateTimeZone;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

class WeeklyRotatingFileHandler extends StreamHandler
{
    public const DEFAULT_RETENTION_DAYS = 90;

    private readonly string $filename;

    private readonly int $retentionDays;

    public function __construct(
        string $filename,
        mixed $retentionDays = self::DEFAULT_RETENTION_DAYS,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = true,
    ) {
        $this->filename = $filename;
        $this->retentionDays = self::normalizeRetentionDays($retentionDays);

        parent::__construct(
            $this->weeklyFilename(new DateTimeImmutable('now', new DateTimeZone('UTC'))),
            $level,
            $bubble,
            $filePermission,
            $useLocking,
        );
    }

    public static function normalizeRetentionDays(mixed $value): int
    {
        $retentionDays = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return $retentionDays === false
            ? self::DEFAULT_RETENTION_DAYS
            : $retentionDays;
    }

    protected function write(LogRecord $record): void
    {
        $weeklyFilename = $this->weeklyFilename($record->datetime);

        if ($this->url !== $weeklyFilename) {
            $this->close();
            $this->url = $weeklyFilename;
        }

        $this->pruneExpiredArchives($record->datetime);

        parent::write($record);
    }

    private function weeklyFilename(DateTimeImmutable $datetime): string
    {
        $weekStart = $datetime
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify('monday this week')
            ->setTime(0, 0);
        $path = pathinfo($this->filename);
        $filename = $path['filename'] . '-' . $weekStart->format('Y-m-d');

        if (isset($path['extension'])) {
            $filename .= '.' . $path['extension'];
        }

        return ($path['dirname'] ?? '.') . DIRECTORY_SEPARATOR . $filename;
    }

    private function pruneExpiredArchives(DateTimeImmutable $datetime): void
    {
        $path = pathinfo($this->filename);
        $directory = $path['dirname'] ?? '.';
        $extension = isset($path['extension']) ? '.' . $path['extension'] : '';
        $pattern = $directory . DIRECTORY_SEPARATOR . $path['filename'] . '-*' . $extension;
        $archivePattern = '/^' . preg_quote($path['filename'], '/') . '-(\d{4}-\d{2}-\d{2})'
            . preg_quote($extension, '/') . '$/';
        $now = $datetime->setTimezone(new DateTimeZone('UTC'));

        foreach (glob($pattern) ?: [] as $archive) {
            if (! preg_match($archivePattern, basename($archive), $matches)) {
                continue;
            }

            $weekStart = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $matches[1],
                new DateTimeZone('UTC'),
            );

            if (
                ! $weekStart
                || $weekStart->format('Y-m-d') !== $matches[1]
                || $weekStart->format('N') !== '1'
            ) {
                continue;
            }

            $expiresAt = $weekStart
                ->modify('+7 days')
                ->modify("+{$this->retentionDays} days");

            if ($expiresAt > $now) {
                continue;
            }

            if (! $this->deleteArchive($archive) && file_exists($archive)) {
                $this->reportCleanupFailure($archive);
            }
        }
    }

    protected function deleteArchive(string $archive): bool
    {
        return @unlink($archive);
    }

    protected function reportCleanupFailure(string $archive): void
    {
        error_log("Unable to delete expired weekly log archive: {$archive}");
    }
}
