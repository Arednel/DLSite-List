<?php

namespace App\Http\Controllers;

use App\Enums\ProductAgeCategory;
use App\Enums\ProductProgress;
use App\Http\Requests\StartBulkImportRequest;
use App\Models\Option;
use App\Support\BulkImport\BulkImportService;
use App\Support\DLSite\DLSiteProductImportInput;
use App\Support\Localization\ErrorMessage;
use App\Support\ProductFieldLayout;
use App\Support\ReturnTarget;
use App\Support\ReturnUrl;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class BulkImportController extends Controller
{
    public function create(Request $request): View
    {
        if (! $request->has('return_query') && $request->old('return_query') !== null) {
            $request->merge(['return_query' => $request->old('return_query')]);
        }

        $returnTarget = ReturnTarget::fromRequest($request);
        $returnUrl = ReturnUrl::fromRequest($request);

        $returnParameters = ['return_url' => $returnUrl];

        if ($request->boolean('modal')) {
            $returnParameters['modal'] = '1';
        }

        if ($returnTarget->query !== []) {
            $returnParameters['return_query'] = $returnTarget->query;
        }

        return view('Create', [
            'isCustomCreate' => false,
            'isBulkImport' => true,
            'isModal' => $request->boolean('modal'),
            'productFormThemeClass' => 'product-form-theme-' . Option::productFormTheme(),
            'quickAddFields' => ProductFieldLayout::bulkImportFields(Option::bulkImportFieldLayout()),
            'returnQuery' => $returnTarget->query,
            'returnUrl' => $returnUrl,
            'returnParameters' => $returnParameters,
            'ageCategoryOptions' => ProductAgeCategory::options(),
            'progressOptions' => ProductProgress::visibleOptions(Option::optionalProductStatuses()),
            ...ProductController::buildDateFieldOptions(),
        ]);
    }

    public function store(
        StartBulkImportRequest $request,
        BulkImportService $service,
    ): RedirectResponse|View {
        $layout = Option::bulkImportFieldLayout();
        $input = DLSiteProductImportInput::fromRequest(
            $request,
            $request->validated(),
            $layout,
        );
        try {
            $run = $service->start($request->productCodes(), $input);
        } catch (RuntimeException $exception) {
            if (! $exception->getPrevious() instanceof LockTimeoutException) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'product_code_list' => ErrorMessage::display($exception->getMessage()),
            ]);
        }
        $redirectUrl = route('options.index', [
            'tab' => 'bulk-imports',
            'bulk_import_run' => $run->getKey(),
        ]);

        if (! $request->boolean('modal')) {
            return redirect($redirectUrl);
        }

        return view('WorkFormCompleted', [
            'redirectUrl' => $redirectUrl,
            'warning' => null,
            'completionStatus' => __('Bulk import started'),
            'completionDescription' => __(':count works were queued. Import continues in the background.', [
                'count' => $run->total_count,
            ]),
        ]);
    }
}
