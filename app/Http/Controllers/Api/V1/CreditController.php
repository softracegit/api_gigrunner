<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class CreditController extends Controller
{
    public function __construct(private CreditService $credits) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'credits' => $this->credits->balances($request->user()),
            'packs' => $this->credits->listPacks(),
        ]);
    }

    public function consume(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:32'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'reason' => ['sometimes', 'string', 'max:80'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        try {
            $balance = $this->credits->consume(
                $request->user(),
                $data['type'],
                $data['amount'] ?? 1,
                $data['reason'] ?? 'consume',
                $data['reference'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'credits' => $this->credits->balances($request->user()),
            ], 402);
        }

        return response()->json([
            'ok' => true,
            'type' => $data['type'],
            'balance' => $balance->balance,
            'credits' => $this->credits->balances($request->user()),
        ]);
    }

    /**
     * Simulate purchase until Stripe exists.
     */
    public function purchaseTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pack' => ['required', 'string', 'max:80'],
        ]);

        try {
            $result = $this->credits->purchasePack($request->user(), $data['pack']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $license = $request->user()->currentLicense();

        return response()->json([
            'ok' => true,
            ...$result,
            'license' => $result['license'] ?? ($license?->toApiArray()),
        ], 201);
    }
}
