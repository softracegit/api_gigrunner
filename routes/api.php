<?php

use App\Http\Controllers\Api\V1\AudioJobController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\LicenseController;
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

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/license', [LicenseController::class, 'show']);
        Route::post('/license/activate-test', [LicenseController::class, 'activateTest']);
        Route::post('/license/revoke', [LicenseController::class, 'revoke']);
        Route::get('/credits', [CreditController::class, 'index']);
        Route::post('/credits/consume', [CreditController::class, 'consume']);
        Route::post('/credits/purchase-test', [CreditController::class, 'purchaseTest']);
        Route::get('/audio/jobs', [AudioJobController::class, 'index']);
        Route::post('/audio/jobs', [AudioJobController::class, 'store']);
        Route::get('/audio/jobs/{uuid}', [AudioJobController::class, 'show']);
        Route::get('/audio/jobs/{uuid}/result', [AudioJobController::class, 'result']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
