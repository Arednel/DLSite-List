<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\AutocompleteController;
use App\Http\Controllers\OptionsController;
use App\Http\Controllers\ProductController;
use App\Support\ReturnTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::controller(ProductController::class)->group(function () {
    // Main Page
    Route::get('/', 'Index')->name('index');

    // Works CRUD logic
    Route::get('/create', 'create')->name('products.create');
    Route::post('/store', 'store')->name('products.store');
    Route::get('/edit/{product}', 'edit')->name('products.edit');
    Route::post('/update/{product}', 'update')->name('products.update');
    Route::post('/destroy/{product}', 'destroy')->name('products.destroy')
        ->missing(fn(Request $request) => redirect(ReturnTarget::fromRequest($request)->toUrl()));

    // Add custom work
    Route::get('/create/custom', 'create_custom')->name('products.create.custom');
    Route::post('/store/custom', 'store_custom')->name('products.store.custom');

    // Tag Library
    Route::get('/tags', 'tagLibrary')->name('tags.index');
});

// Options
Route::controller(OptionsController::class)->group(function () {
    Route::get('/options', 'index')->name('options.index');
    Route::post('/options/refetch-tags', 'startRefetchTags')->name('options.refetch-tags.start');
    Route::get('/options/refetch-tags/{run}', 'showRefetchTags')->name('options.refetch-tags.show');
    Route::get('/options/refetch-tags/{run}/status', 'refetchTagsStatus')->name('options.refetch-tags.status');
    Route::post('/options/refetch-tags/{run}/cancel', 'cancelRefetchTags')->name('options.refetch-tags.cancel');
    Route::post('/options/refetch-tags/{run}/apply', 'applyRefetchTags')->name('options.refetch-tags.apply');
});

// Autocomplete
Route::controller(AutocompleteController::class)->group(function () {
    Route::get('/autocomplete/tags', 'tags')->name('autocomplete.tags');
    Route::get('/autocomplete/series', 'series')->name('autocomplete.series');
});

// Optional authentication
Route::controller(AuthenticationController::class)->group(function () {
    Route::get('/login', 'login')->name('login');
    Route::post('/login', 'authenticate')->name('login.authenticate');
    Route::post('/logout', 'logout')->name('logout');
    Route::get('/admin/setup', 'setup')->name('admin.setup');
    Route::post('/admin/setup', 'storeAdmin')->name('admin.setup.store');
    Route::get('/forgot-password', 'help')->name('password.help');
    Route::get('/admin/password-reset', 'recovery')->name('admin.recovery');
    Route::post('/admin/password-reset', 'resetFromEnvironment')->name('admin.recovery.store');
});
