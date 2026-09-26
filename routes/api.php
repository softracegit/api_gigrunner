<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — base: /api/v1
|--------------------------------------------------------------------------
|
| Production target later: https://gigrunner.studio/api/v1/
| Local: http://127.0.0.1:8000/api/v1/
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'env' => config('app.env'),
        ]);
    });

    // Auth (to be implemented): login, logout, license check
    // Route::post('/login', ...);
    // Route::middleware('auth:sanctum')->group(function () {
    //     Route::get('/me', ...);
    //     Route::get('/license', ...);
    //     Route::post('/logout', ...);
    // });

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});
