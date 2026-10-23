<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portal\AuditWriter;
use App\Services\Portal\RecordAccess;
use App\Services\Portal\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function setup(Request $request, Totp $totp): View
    {
        $user = $this->privileged($request);
        if (! is_string($request->session()->get('portal.mfa_pending'))) {
            $request->session()->put('portal.mfa_pending', $totp->generateSecret());
        }

        $secret = (string) $request->session()->get('portal.mfa_pending');

        return view('portal.mfa-setup', [
            'secret' => $secret,
            'uri' => $totp->provisioningUri($secret, $user->email),
        ]);
    }

    public function confirm(Request $request, Totp $totp, RecordAccess $events, AuditWriter $audit): RedirectResponse
    {
        $user = $this->privileged($request);
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);
        $secret = (string) $request->session()->get('portal.mfa_pending');
        if ($secret === '' || ! $totp->verify($secret, $data['code'])) {
            $events->write($request, 'mfa_failed', $user);

            return back()->withErrors(['code' => 'That authentication code is not valid.']);
        }

        $plain = [];
        $hashes = [];
        for ($index = 0; $index < 8; $index++) {
            $code = Str::lower(Str::random(4).'-'.Str::random(4));
            $plain[] = $code;
            $hashes[] = Hash::make($code);
        }

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $hashes,
            'mfa_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('portal.mfa_pending');
        $request->session()->put('portal.mfa_user', $user->id);
        $events->write($request, 'mfa_enabled', $user);
        $audit->write($user, 'account.mfa_enabled', $user, [], $request->ip());

        return redirect()->route('portal.home')->with('recovery_codes', $plain);
    }

    public function challenge(): View
    {
        return view('portal.mfa-challenge');
    }

    public function verify(Request $request, Totp $totp, RecordAccess $events): RedirectResponse
    {
        $user = $this->privileged($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $code = str_replace(' ', '', $data['code']);
        $passed = is_string($user->mfa_secret) && $totp->verify($user->mfa_secret, $code);
        if (! $passed) {
            $passed = $this->consumeRecoveryCode($user, $code);
        }

        if (! $passed) {
            $events->write($request, 'mfa_failed', $user);

            return back()->withErrors(['code' => 'That authentication code is not valid.']);
        }

        $request->session()->put('portal.mfa_user', $user->id);
        $events->write($request, 'mfa_passed', $user);

        return redirect()->route('portal.home');
    }

    private function privileged(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->requiresMfa(), 403);

        return $user;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashes = $user->mfa_recovery_codes ?? [];
        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $user->forceFill(['mfa_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }
}
