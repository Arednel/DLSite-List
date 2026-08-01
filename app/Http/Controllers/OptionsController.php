<?php

namespace App\Http\Controllers;

use App\Models\Option;
use App\Models\RefetchRun;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OptionsController extends Controller
{
    private const TABS = [
        'general',
        'field-layouts',
        'authentication',
        'refetch',
    ];

    public function index(Request $request): View
    {
        $activeTab = $this->activeTab($request);

        return view('Options', [
            'activeTab' => $activeTab,
            'latestRefetchRunId' => $activeTab === 'refetch'
                ? RefetchRun::query()->latest('id')->value('id')
                : null,
            ...$this->productFormModalSettings(),
        ]);
    }

    private function activeTab(Request $request): string
    {
        $activeTab = $request->old('tab', $request->query('tab', 'general'));

        return is_string($activeTab) && in_array($activeTab, self::TABS, true)
            ? $activeTab
            : 'general';
    }

    /**
     * @return array{productFormModalEnabled: bool, productFormModalCompletionAction: string}
     */
    private function productFormModalSettings(): array
    {
        return [
            'productFormModalEnabled' => Option::productFormModalEnabled(),
            'productFormModalCompletionAction' => Option::productFormModalCompletionAction(),
        ];
    }
}
