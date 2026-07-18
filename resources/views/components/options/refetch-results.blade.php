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
                        <span class="result-indicators" aria-label="{{ __('Tag changes') }}">
                            @if ($result->hasAddedJapaneseTags())
                                <span class="result-indicator result-indicator--new"
                                    title="{{ __('New JP tags') }}">+JP</span>
                            @endif
                            @if ($result->hasAddedEnglishTags())
                                <span class="result-indicator result-indicator--new"
                                    title="{{ __('New EN tags') }}">+EN</span>
                            @endif
                            @if ($result->hasStaleJapaneseTags())
                                <span class="result-indicator result-indicator--stale"
                                    title="{{ __('Stale JP tags') }}">-JP</span>
                            @endif
                            @if ($result->hasStaleEnglishTags())
                                <span class="result-indicator result-indicator--stale"
                                    title="{{ __('Stale EN tags') }}">-EN</span>
                            @endif
                            @if ($result->hasCustomToFetchedTags())
                                <span class="result-indicator result-indicator--new"
                                    title="{{ __('Custom tags now fetched') }}">C->F</span>
                            @endif
                        </span>
                    @endif

                    <span class="result-title">
                        <strong>{{ $result->product_id }}</strong>
                        {{ $result->product?->work_name ?? __('Missing product') }}
                    </span>
                </span>
                <span class="result-status result-status--{{ $result->status }}">{{ $result->statusLabel() }}</span>
            </summary>

            @if ($result->isSkipped())
                <p class="notice notice--error">{{ $result->displayError() }}</p>
            @else
                <div class="result-grid">
                    <div>
                        <h3>{{ __('Fetched JP') }}</h3>
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
                                <span class="empty-state">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>{{ __('Fetched EN') }}</h3>
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
                                <span class="empty-state">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>{{ __('New JP') }}</h3>
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
                                <span class="empty-state">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>{{ __('New EN') }}</h3>
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
                                <span class="empty-state">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>{{ __('Stale JP') }}</h3>
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
                                <span class="empty-state">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>{{ __('Stale EN') }}</h3>
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
                                <span class="empty-state">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>{{ __('Custom->Fetched JP') }}</h3>
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
                                <span class="empty-state">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3>{{ __('Custom->Fetched EN') }}</h3>
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
                                <span class="empty-state">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($showControls)
                    <div class="review-actions review-actions--compact">
                        <label>
                            {{ __('This work New JP') }}
                            <select name="work_actions[{{ $result->product_id }}][added_japanese]">
                                <option value="inherit" selected>{{ __('Use global choice') }}</option>
                                <option value="{{ $addAction }}">{{ __('Add as fetched') }}</option>
                                <option value="{{ $ignoreAction }}">{{ __('Ignore') }}</option>
                            </select>
                        </label>
                        <label>
                            {{ __('This work New EN') }}
                            <select name="work_actions[{{ $result->product_id }}][added_english]">
                                <option value="inherit" selected>{{ __('Use global choice') }}</option>
                                <option value="{{ $addAction }}">{{ __('Add as fetched') }}</option>
                                <option value="{{ $ignoreAction }}">{{ __('Ignore') }}</option>
                            </select>
                        </label>
                        <label>
                            {{ __('This work Stale JP') }}
                            <select name="work_actions[{{ $result->product_id }}][japanese]">
                                <option value="inherit" selected>{{ __('Use global choice') }}</option>
                                <option value="{{ $moveAction }}">{{ __('Move to custom tags') }}</option>
                                <option value="{{ $removeAction }}">{{ __('Remove') }}</option>
                            </select>
                        </label>
                        <label>
                            {{ __('This work Stale EN') }}
                            <select name="work_actions[{{ $result->product_id }}][english]">
                                <option value="inherit" selected>{{ __('Use global choice') }}</option>
                                <option value="{{ $moveAction }}">{{ __('Move to custom tags') }}</option>
                                <option value="{{ $removeAction }}">{{ __('Remove') }}</option>
                            </select>
                        </label>
                        <label>
                            {{ __('This work Custom->Fetched') }}
                            <select name="work_actions[{{ $result->product_id }}][custom_to_fetched]">
                                <option value="inherit" selected>{{ __('Use global choice') }}</option>
                                <option value="{{ $promoteCustomAction }}">{{ __('Promote to fetched') }}</option>
                                <option value="{{ $keepCustomAction }}">{{ __('Keep custom') }}</option>
                            </select>
                        </label>
                    </div>
                @endif
            @endif
        </details>
    @endforeach
</div>
