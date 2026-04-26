<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProductController;
use App\Http\Controllers\OptionsController;

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

// Main Page
Route::get('/', [ProductController::class, 'Index'])->name('index');

// Works CRUD logic
Route::get('/create', [ProductController::class, 'create'])->name('products.create');
Route::post('/store', [ProductController::class, 'store'])->name('products.store');
Route::get('/edit/{id}', [ProductController::class, 'edit'])->name('products.edit');
Route::post('/update/{id}', [ProductController::class, 'update'])->name('products.update');
Route::post('/destroy/{id}', [ProductController::class, 'destroy'])->name('products.destroy');

// Add custom work
Route::get('/create/custom', [ProductController::class, 'create_custom'])->name('products.create.custom');
Route::post('/store/custom', [ProductController::class, 'store_custom'])->name('products.store.custom');

// Tag Library
Route::get('/tags', [ProductController::class, 'tagLibrary'])->name('tags.index');

// Options
Route::get('/options', [OptionsController::class, 'index'])->name('options.index');
Route::post('/options/refetch-tags', [OptionsController::class, 'startRefetchTags'])->name('options.refetch-tags.start');
Route::get('/options/refetch-tags/{run}', [OptionsController::class, 'showRefetchTags'])->name('options.refetch-tags.show');
Route::get('/options/refetch-tags/{run}/status', [OptionsController::class, 'refetchTagsStatus'])->name('options.refetch-tags.status');
Route::post('/options/refetch-tags/{run}/apply', [OptionsController::class, 'applyRefetchTags'])->name('options.refetch-tags.apply');
