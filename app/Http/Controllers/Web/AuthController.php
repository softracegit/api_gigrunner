<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account')->with('status', 'Conta criada.');
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'As credenciais estão incorrectas.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function account(Request $request): View
    {
        $user = $request->user();

        return view('auth.account', [
            'user' => $user,
            'license' => $user->currentLicense(),
        ]);
    }

    public function activateTestLicense(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->licenses()->create([
            'plan' => 'trial',
            'status' => License::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return redirect()->route('account')->with('status', 'Licença de teste activada por 30 dias.');
    }

    public function revokeLicense(Request $request): RedirectResponse
    {
        $license = $request->user()->currentLicense();

        if ($license) {
            $license->update(['status' => License::STATUS_REVOKED]);
        }

        return redirect()->route('account')->with('status', 'Licença revogada.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
