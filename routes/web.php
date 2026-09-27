<?php

use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'))->name('home');
Route::get('/docs', fn () => view('docs'))->name('docs');
Route::get('/playground', fn () => view('playground'))->name('playground');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/account', [AuthController::class, 'account'])->name('account');
    Route::post('/account/activate-test-license', [AuthController::class, 'activateTestLicense'])->name('account.activate-test-license');
    Route::post('/account/revoke-license', [AuthController::class, 'revokeLicense'])->name('account.revoke-license');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
