<?php

namespace Tests\Unit\Support\TagRefetch;

use App\Support\DLSite\DLSitePythonRunner;
use App\Support\TagRefetch\DLSiteTagFetcher;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class DLSiteTagFetcherTest extends TestCase
{
    public function test_it_parses_and_normalizes_fetched_tag_json(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode([
                'japanese' => [
                    'genre' => [' JP Tag ', 'JP Tag', '', null],
                ],
                'english' => [
                    'genre' => ['EN Tag', ' EN Other '],
                ],
            ])),
        ])->preventStrayProcesses();

        $tags = (new DLSiteTagFetcher(new DLSitePythonRunner))->fetch('RJ123456');

        $this->assertSame([
            'japanese' => ['JP Tag'],
            'english' => ['EN Tag', 'EN Other'],
        ], $tags);
    }

    public function test_failed_process_prefers_stderr_message(): void
    {
        Process::fake([
            '*' => Process::result(
                output: 'stdout failure',
                errorOutput: 'stderr failure',
                exitCode: 1,
            ),
        ])->preventStrayProcesses();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stderr failure');

        (new DLSiteTagFetcher(new DLSitePythonRunner))->fetch('RJ123456');
    }

    public function test_failed_process_falls_back_to_stdout_message(): void
    {
        Process::fake([
            '*' => Process::result(
                output: 'stdout failure',
                exitCode: 1,
            ),
        ])->preventStrayProcesses();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stdout failure');

        (new DLSiteTagFetcher(new DLSitePythonRunner))->fetch('RJ123456');
    }

    public function test_invalid_json_throws_existing_invalid_json_message(): void
    {
        Process::fake([
            '*' => Process::result(output: 'not-json'),
        ])->preventStrayProcesses();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DLSite tag fetch returned invalid JSON.');

        (new DLSiteTagFetcher(new DLSitePythonRunner))->fetch('RJ123456');
    }
}
