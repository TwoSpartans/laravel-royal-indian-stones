<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portal\AuditWriter;
use App\Services\Portal\RecordAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function request(): View
    {
        return view('portal.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($data['email'])])->first();
        if ($user instanceof User && $user->portal_role !== null && $user->password_set_at !== null && $user->deactivated_at === null) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'If a staff account matches that email, a reset link is on its way.');
    }

    public function reset(string $token): View
    {
        return view('portal.reset-password', [
            'token' => $token,
            'email' => request()->string('email')->toString(),
        ]);
    }

    public function update(Request $request, RecordAccess $events, AuditWriter $audit): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) use ($request, $events, $audit, $data): void {
                if ($user->portal_role === null || $user->deactivated_at !== null) {
                    abort(403);
                }

                $user->forceFill([
                    'password' => $password,
                    'password_set_at' => now(),
                ])->save();

                $events->write($request, 'password_reset', $user, null, $data['email']);
                $audit->write($user, 'account.password_reset', $user, [], $request->ip());
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This reset link is invalid or has expired.'])->onlyInput('email');
        }

        return redirect()->route('portal.login')->with('status', 'Your password is updated. Sign in with the new password.');
    }

    public function change(Request $request, AuditWriter $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is not correct.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'password_set_at' => now(),
        ])->save();
        $audit->write($user, 'account.password_changed', $user, [], $request->ip());

        return back()->with('status', 'Password updated.');
    }
}
