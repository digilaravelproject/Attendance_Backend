<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/privacy-policy', [PageController::class, 'show'])
    ->defaults('pageType', 'privacy_policy')
    ->name('pages.privacy-policy');

Route::get('/terms-condition', [PageController::class, 'show'])
    ->defaults('pageType', 'terms_condition')
    ->name('pages.terms-condition');

Route::get('/contact-us', [PageController::class, 'show'])
    ->defaults('pageType', 'contact_us')
    ->name('pages.contact-us');
