@props([
    'run',
    'moveAction',
    'removeAction',
    'addAction',
    'ignoreAction',
    'promoteCustomAction',
    'keepCustomAction',
    'showControls',
    'tagRows' => [],
])

<div class="result-list">
    @foreach ($run->results as $result)
        <details class="result-card">
            <summary>
                <span class="result-heading">
                    @if ($result->hasTagChanges())
                        <span class="result-indicators" aria-label="Tag changes">
                            @if ($result->hasAddedJapaneseTags())
                                <span class="result-indicator result-indicator--new" title="New JP tags">+JP</span>
                            @endif
                            @if ($result->hasAddedEnglishTags())
                                <span class="result-indicator result-indicator--new" title="New EN tags">+EN</span>
                            @endif
                            @if ($result->hasStaleJapaneseTags())
                                <span class="result-indicator result-indicator--stale" title="Stale JP tags">-JP</span>
                            @endif
                            @if ($result->hasStaleEnglishTags())
                                <span class="result-indicator result-indicator--stale" title="Stale EN tags">-EN</span>
                            @endif
                            @if ($result->hasCustomToFetchedTags())
                                <span class="result-indicator result-indicator--new"
                                    title="Custom tags now fetched">C->F</span>
                            @endif
                        </span>
                    @endif

                    <span class="result-title">
                        <strong>{{ $result->product_id }}</strong>
                        {{ $result->product?->work_name ?? 'Missing product' }}
                    </span>
                </span>
                <span class="result-status result-status--{{ $result->status }}">{{ $result->status }}</span>
            </summary>

            @if ($result->isSkipped())
                <p class="notice notice--error">{{ $result->error }}</p>
            @else
                <div class="result-grid">
                    <div>
                        <h3>Fetched JP</h3>
                        <div class="tag-row">
                            @forelse ($tagRows[$result->getKey()]['fetched_japanese_tags'] ?? [] as $tag)
                                <span @class([
                                    'tag',
                                    'tag--sm',
                                    'tag--background-colored' => $tag['has_background_color'],
                                    'tag--text-colored' => $tag['has_font_color'],
                                ])
                                    @if (filled($tag['color_style'])) style="{{ $tag['color_style'] }}" @endif>{{ $tag['title'] }}</span>
                            @empty
                                <span class="empty-state">None</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>Fetched EN</h3>
                        <div class="tag-row">
                            @forelse ($tagRows[$result->getKey()]['fetched_english_tags'] ?? [] as $tag)
                                <span @class([
                                    'tag',
                                    'tag--sm',
                                    'tag--background-colored' => $tag['has_background_color'],
                                    'tag--text-colored' => $tag['has_font_color'],
                                ])
                                    @if (filled($tag['color_style'])) style="{{ $tag['color_style'] }}" @endif>{{ $tag['title'] }}</span>
                            @empty
                                <span class="empty-state">None</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>New JP</h3>
                        <div class="tag-row">
                            @forelse ($tagRows[$result->getKey()]['added_japanese_tags'] ?? [] as $tag)
                                <span @class([
                                    'tag',
                                    'tag--soft',
                                    'tag--sm',
                                    'tag--background-colored' => $tag['has_background_color'],
                                    'tag--text-colored' => $tag['has_font_color'],
                                ])
                                    @if (filled($tag['color_style'])) style="{{ $tag['color_style'] }}" @endif>{{ $tag['title'] }}</span>
                            @empty
                                <span class="empty-state">None</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>New EN</h3>
                        <div class="tag-row">
                            @forelse ($tagRows[$result->getKey()]['added_english_tags'] ?? [] as $tag)
                                <span @class([
                                    'tag',
                                    'tag--soft',
                                    'tag--sm',
                                    'tag--background-colored' => $tag['has_background_color'],
                                    'tag--text-colored' => $tag['has_font_color'],
                                ])
                                    @if (filled($tag['color_style'])) style="{{ $tag['color_style'] }}" @endif>{{ $tag['title'] }}</span>
                            @empty
                                <span class="empty-state">None</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>Stale JP</h3>
                        <div class="tag-row">
                            @forelse ($tagRows[$result->getKey()]['stale_japanese_tags'] ?? [] as $tag)
                                <span @class([
                                    'tag',
                                    'tag--outline',
                                    'tag--sm',
                                    'tag--background-colored' => $tag['has_background_color'],
                                    'tag--text-colored' => $tag['has_font_color'],
                                ])
                                    @if (filled($tag['color_style'])) style="{{ $tag['color_style'] }}" @endif>{{ $tag['title'] }}</span>
                            @empty
                                <span class="empty-state">None</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>Stale EN</h3>
                        <div class="tag-row">
                            @forelse ($tagRows[$result->getKey()]['stale_english_tags'] ?? [] as $tag)
                                <span @class([
                                    'tag',
                                    'tag--outline',
                                    'tag--sm',
                                    'tag--background-colored' => $tag['has_background_color'],
                                    'tag--text-colored' => $tag['has_font_color'],
                                ])
                                    @if (filled($tag['color_style'])) style="{{ $tag['color_style'] }}" @endif>{{ $tag['title'] }}</span>
                            @empty
                                <span class="empty-state">None</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>Custom -> Fetched JP</h3>
                        <div class="tag-row">
                            @forelse ($tagRows[$result->getKey()]['custom_to_fetched_japanese_tags'] ?? [] as $tag)
                                <span @class([
                                    'tag',
                                    'tag--soft',
                                    'tag--sm',
                                    'tag--background-colored' => $tag['has_background_color'],
                                    'tag--text-colored' => $tag['has_font_color'],
                                ])
                                    @if (filled($tag['color_style'])) style="{{ $tag['color_style'] }}" @endif>{{ $tag['title'] }}</span>
                            @empty
                                <span class="empty-state">None</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>Custom -> Fetched EN</h3>
                        <div class="tag-row">
                            @forelse ($tagRows[$result->getKey()]['custom_to_fetched_english_tags'] ?? [] as $tag)
                                <span @class([
                                    'tag',
                                    'tag--soft',
                                    'tag--sm',
                                    'tag--background-colored' => $tag['has_background_color'],
                                    'tag--text-colored' => $tag['has_font_color'],
                                ])
                                    @if (filled($tag['color_style'])) style="{{ $tag['color_style'] }}" @endif>{{ $tag['title'] }}</span>
                            @empty
                                <span class="empty-state">None</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($showControls)
                    <div class="review-actions review-actions--compact">
                        <label>
                            This work New JP
                            <select name="work_actions[{{ $result->product_id }}][added_japanese]">
                                <option value="inherit" selected>Use global choice</option>
                                <option value="{{ $addAction }}">Add as fetched</option>
                                <option value="{{ $ignoreAction }}">Ignore</option>
                            </select>
                        </label>
                        <label>
                            This work New EN
                            <select name="work_actions[{{ $result->product_id }}][added_english]">
                                <option value="inherit" selected>Use global choice</option>
                                <option value="{{ $addAction }}">Add as fetched</option>
                                <option value="{{ $ignoreAction }}">Ignore</option>
                            </select>
                        </label>
                        <label>
                            This work Stale JP
                            <select name="work_actions[{{ $result->product_id }}][japanese]">
                                <option value="inherit" selected>Use global choice</option>
                                <option value="{{ $moveAction }}">Move to custom tags</option>
                                <option value="{{ $removeAction }}">Remove</option>
                            </select>
                        </label>
                        <label>
                            This work Stale EN
                            <select name="work_actions[{{ $result->product_id }}][english]">
                                <option value="inherit" selected>Use global choice</option>
                                <option value="{{ $moveAction }}">Move to custom tags</option>
                                <option value="{{ $removeAction }}">Remove</option>
                            </select>
                        </label>
                        <label>
                            This work Custom -> Fetched
                            <select name="work_actions[{{ $result->product_id }}][custom_to_fetched]">
                                <option value="inherit" selected>Use global choice</option>
                                <option value="{{ $promoteCustomAction }}">Promote to fetched</option>
                                <option value="{{ $keepCustomAction }}">Keep custom</option>
                            </select>
                        </label>
                    </div>
                @endif
            @endif
        </details>
    @endforeach
</div>
