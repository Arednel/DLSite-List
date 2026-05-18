<?php

namespace Tests\Unit\Support;

use App\Support\ReturnTarget;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReturnTargetTest extends TestCase
{
    public function test_it_normalizes_index_return_state_from_request(): void
    {
        $target = ReturnTarget::fromRequest(Request::create('/create', 'GET', [
            'return_query' => [
                'progress' => 'Listening',
                'priority' => '-1',
                'search' => 'rain',
                'page' => '3',
            ],
            'return_fragment' => ' RJ123456 ',
        ]));

        $this->assertSame([
            'search' => 'rain',
            'progress' => 'Listening',
            'page' => '3',
        ], $target->query);
        $this->assertSame('RJ123456', $target->fragment);
        $this->assertSame('/?search=rain&progress=Listening&page=3#RJ123456', $target->toUrl());
    }

    public function test_it_drops_invalid_index_return_pages(): void
    {
        foreach (['0', '-1', '1.5', 'abc', '01'] as $page) {
            $target = ReturnTarget::fromRequest(Request::create('/create', 'GET', [
                'return_query' => [
                    'search' => 'rain',
                    'page' => $page,
                ],
            ]));

            $this->assertSame(['search' => 'rain'], $target->query);
        }
    }

    public function test_it_ignores_return_routes_and_keeps_index_query_only(): void
    {
        $target = ReturnTarget::fromRequest(Request::create('/create', 'GET', [
            'return_route' => 'tags.index',
            'return_query' => [
                'search' => 'rain',
            ],
            'return_fragment' => 'RJ123456',
        ]));

        $this->assertSame(['search' => 'rain'], $target->query);
        $this->assertSame('RJ123456', $target->fragment);
        $this->assertSame('/?search=rain#RJ123456', $target->toUrl());
    }

    public function test_it_defaults_malformed_return_input_to_index(): void
    {
        $target = ReturnTarget::fromRequest(Request::create('/create', 'GET', [
            'return_route' => ['not-valid'],
            'return_query' => 'not-an-array',
            'return_fragment' => ['RJ123456'],
        ]));

        $this->assertSame([], $target->query);
        $this->assertNull($target->fragment);
        $this->assertSame('/', $target->toUrl());
    }

    public function test_it_can_update_index_progress_and_append_fragment(): void
    {
        $target = ReturnTarget::fromRequest(Request::create('/edit/RJ123456', 'POST', [
            'return_query' => [
                'age_category' => 'ALL_AGES',
                'progress' => 'Listening',
                'search' => 'rain',
                'page' => '4',
            ],
            'return_fragment' => 'RJ123456',
        ]))->withIndexProgress('Completed');

        $this->assertSame('/?search=rain&age_category=ALL_AGES&progress=Completed#RJ123456', $target->toUrl());
    }
}
