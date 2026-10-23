<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portal\DeviceRegistrar;
use App\Services\Portal\RecordAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function create(): View
    {
        return view('portal.login');
    }

    public function store(Request $request, RecordAccess $events, DeviceRegistrar $devices): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($credentials['email']);
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $passwordMatches = Hash::check($credentials['password'], $user->password ?? self::DUMMY_HASH);
        $canEnter = $user instanceof User
            && $user->portal_role !== null
            && $user->deactivated_at === null
            && $user->password_set_at !== null
            && $passwordMatches;

        if (! $canEnter) {
            $events->write($request, 'login_failed', $user instanceof User && $user->portal_role !== null ? $user : null, null, $email);

            return back()->withErrors([
                'email' => 'Those details do not match a staff account.',
            ])->onlyInput('email');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('portal.mfa_user');

        $synced = $devices->sync($request, $user);
        $events->write($request, 'login', $user, $synced['device']);

        $redirect = $user->requiresMfa()
            ? ($user->mfa_confirmed_at === null ? route('portal.mfa.setup') : route('portal.mfa.challenge'))
            : route('portal.home');

        $response = redirect()->to($redirect);
        if ($synced['cookie'] !== null) {
            $response->withCookie($synced['cookie']);
        }

        return $response;
    }

    public function destroy(Request $request, RecordAccess $events): RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User && $user->portal_role !== null) {
            $events->write($request, 'logout', $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
