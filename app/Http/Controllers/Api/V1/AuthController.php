<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private CreditService $credits) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data);
        $token = $user->createToken('gigrunner')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'license' => License::inactivePayload(),
            'credits' => $this->credits->balances($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais estão incorrectas.'],
            ]);
        }

        $token = $user->createToken('gigrunner')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'license' => $this->licensePayload($user),
            'credits' => $this->credits->balances($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'license' => $this->licensePayload($user),
            'credits' => $this->credits->balances($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function licensePayload(User $user): array
    {
        $license = $user->licenses()->latest('id')->first();

        return $license
            ? $license->toApiArray()
            : License::inactivePayload();
    }
}
