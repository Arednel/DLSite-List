<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DLSiteScraperController;

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

//Main Page
Route::view('/', 'Index')->name('index');
Route::view('/index', 'Index');
Route::view('/Index', 'Index');


Route::get('/Scrape', [DLSiteScraperController::class, 'Scrape']);

Route::view('/Edit', 'Edit');
Route::view('/edit', 'Edit');
