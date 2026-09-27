<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'license' => $this->currentLicensePayload($request),
        ]);
    }

    /**
     * Temporary helper until Stripe/checkout exists.
     * Creates a 30-day trial license for the authenticated user.
     */
    public function activateTest(Request $request): JsonResponse
    {
        $license = $request->user()->licenses()->create([
            'plan' => 'trial',
            'status' => License::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'license' => $license->toApiArray(),
        ], 201);
    }

    public function revoke(Request $request): JsonResponse
    {
        $license = $request->user()->licenses()->latest('id')->first();

        if (! $license) {
            return response()->json([
                'license' => License::inactivePayload(),
                'message' => 'Não existe licença para revogar.',
            ]);
        }

        $license->update(['status' => License::STATUS_REVOKED]);

        return response()->json([
            'license' => $license->fresh()->toApiArray(),
        ]);
    }

    private function currentLicensePayload(Request $request): array
    {
        $license = $request->user()->licenses()->latest('id')->first();

        return $license
            ? $license->toApiArray()
            : License::inactivePayload();
    }
}
