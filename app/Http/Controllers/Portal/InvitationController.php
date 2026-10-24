<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\PortalInvitation;
use App\Services\Portal\DeviceRegistrar;
use App\Services\Portal\RecordAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->invitation($token);

        return view('portal.invitation', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token, RecordAccess $events, DeviceRegistrar $devices): RedirectResponse
    {
        $invitation = $this->invitation($token);
        $data = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
            'acknowledge' => ['accepted'],
        ], [
            'acknowledge.accepted' => 'Read the data notice and confirm it before continuing.',
        ]);

        $user = $invitation->user;
        abort_unless($user !== null, 404);

        $status = $user->profile?->probation_ends_on !== null && $user->profile->probation_ends_on->isFuture()
            ? EmploymentStatus::Probation
            : EmploymentStatus::Active;

        $user->forceFill([
            'password' => $data['password'],
            'password_set_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        $user->profile?->forceFill([
            'employment_status' => $status,
            'data_notice_acknowledged_at' => now(),
            'data_notice_version' => config('portal.notice_version'),
        ])->save();

        $invitation->forceFill(['accepted_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $synced = $devices->sync($request, $user);
        $events->write($request, 'invitation_accepted', $user, $synced['device']);

        $redirect = $user->requiresMfa()
            ? route('portal.mfa.setup')
            : route('portal.home');

        $response = redirect()->to($redirect)->with('status', 'Your staff account is ready.');
        if ($synced['cookie'] !== null) {
            $response->withCookie($synced['cookie']);
        }

        return $response;
    }

    private function invitation(string $token): PortalInvitation
    {
        $invitation = PortalInvitation::query()
            ->with('user.profile')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null) {
            abort(404);
        }

        if (! $invitation->isOpen()) {
            throw new HttpException(410, 'This invitation is no longer available.');
        }

        return $invitation;
    }
}
