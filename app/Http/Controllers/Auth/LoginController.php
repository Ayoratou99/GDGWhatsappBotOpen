<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('admin')) {
            return redirect()->route('conversations.index');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! $this->matches($credentials['username'], $credentials['password'])) {
            Log::warning('Tentative de connexion refusée.', ['ip' => $request->ip()]);

            // Ralentit le bruteforce, et le message reste volontairement vague.
            sleep(1);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Identifiants incorrects.']);
        }

        $request->session()->regenerate();
        $request->session()->put('admin', true);

        return redirect()->intended(route('conversations.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Comparaison à temps constant, et refus net si le compte n'est pas
     * configuré : hash_equals('', '') vaut true.
     */
    private function matches(string $username, string $password): bool
    {
        $expectedUsername = (string) config('admin.username');
        $expectedPassword = (string) config('admin.password');

        if ($expectedUsername === '' || $expectedPassword === '') {
            Log::error('ADMIN_USERNAME ou ADMIN_PASSWORD absent : connexion impossible.');

            return false;
        }

        return hash_equals($expectedUsername, $username)
            && hash_equals($expectedPassword, $password);
    }
}
