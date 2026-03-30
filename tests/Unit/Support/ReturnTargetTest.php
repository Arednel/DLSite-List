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
            'return_route' => 'index',
            'return_query' => [
                'progress' => 'Listening',
                'priority' => '-1',
                'search' => 'rain',
            ],
            'return_fragment' => ' RJ123456 ',
        ]));

        $this->assertSame('index', $target->route);
        $this->assertSame([
            'search' => 'rain',
            'progress' => 'Listening',
        ], $target->query);
        $this->assertSame('RJ123456', $target->fragment);
        $this->assertSame('/?search=rain&progress=Listening#RJ123456', $target->toUrl());
    }

    public function test_it_drops_query_and_fragment_for_non_index_routes(): void
    {
        $target = ReturnTarget::fromRequest(Request::create('/create', 'GET', [
            'return_route' => 'tags.index',
            'return_query' => [
                'search' => 'rain',
            ],
            'return_fragment' => 'RJ123456',
        ]));

        $this->assertSame('tags.index', $target->route);
        $this->assertSame([], $target->query);
        $this->assertNull($target->fragment);
        $this->assertSame('/tags', $target->toUrl());
    }

    public function test_it_defaults_invalid_routes_to_index(): void
    {
        $target = ReturnTarget::fromRequest(Request::create('/create', 'GET', [
            'return_route' => 'not-valid',
        ]));

        $this->assertSame('index', $target->route);
        $this->assertSame('/', $target->toUrl());
    }

    public function test_it_can_update_index_progress_and_append_fragment(): void
    {
        $target = ReturnTarget::fromRequest(Request::create('/edit/RJ123456', 'POST', [
            'return_route' => 'index',
            'return_query' => [
                'age_category' => 'ALL_AGES',
                'progress' => 'Listening',
                'search' => 'rain',
            ],
            'return_fragment' => 'RJ123456',
        ]))->withIndexProgress('Completed');

        $this->assertSame('/?search=rain&age_category=ALL_AGES&progress=Completed#RJ123456', $target->toUrl());
    }

    public function test_it_only_adds_fragments_for_index_routes(): void
    {
        $indexTarget = ReturnTarget::fromRequest(Request::create('/create', 'GET'))
            ->withFragment('RJ123456');
        $tagsTarget = ReturnTarget::fromRequest(Request::create('/create', 'GET', [
            'return_route' => 'tags.index',
        ]))->withFragment('RJ123456');

        $this->assertSame('RJ123456', $indexTarget->fragment);
        $this->assertNull($tagsTarget->fragment);
        $this->assertSame('/tags', $tagsTarget->toUrl());
    }
}
