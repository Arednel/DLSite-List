<?php

namespace Tests\Unit\Http;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for CVE-2026-108082 in symfony/http-foundation.
 *
 * Every incoming request parses its Cache-Control header while the Request is
 * being built. Before 7.4.21, whitespace in front of a separator made that
 * parser backtrack exponentially: a crafted header burned CPU on each request,
 * and longer runs hit pcre.backtrack_limit and dropped every directive.
 */
class CacheControlHeaderParsingTest extends TestCase
{
    public function test_whitespace_before_a_separator_keeps_cache_control_directives(): void
    {
        $request = Request::create('/', 'GET', server: [
            'HTTP_CACHE_CONTROL' => 'max-age=60' . str_repeat(' ', 32) . ', no-cache',
        ]);

        $this->assertSame('60', $request->headers->getCacheControlDirective('max-age'));
        $this->assertTrue($request->headers->getCacheControlDirective('no-cache'));
    }
}
