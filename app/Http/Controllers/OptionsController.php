<?php

namespace App\Http\Controllers;

use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OptionsController extends Controller
{
    private const SECTIONS = [
        'general' => 'General',
        'field-layouts' => 'Field Layouts',
        'authentication' => 'Authentication',
        'refetch' => 'Refetch',
        'transfers' => 'Import / Export',
    ];

    public function index(Request $request): View
    {
        $activeTab = $this->activeTab($request);

        return view('Options', [
            'activeTab' => $activeTab,
            'optionSections' => self::SECTIONS,
            ...$this->productFormModalSettings(),
        ]);
    }

    private function activeTab(Request $request): string
    {
        $activeTab = $request->old('tab', $request->query('tab', 'general'));

        return is_string($activeTab) && array_key_exists($activeTab, self::SECTIONS)
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
