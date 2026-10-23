<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait SignsInToPortal
{
    protected function signIn(User $user): static
    {
        $session = [];
        if ($user->requiresMfa()) {
            $session['portal.mfa_user'] = $user->id;
        }

        return $this->actingAs($user)->withSession($session);
    }

    protected function leavePortal(): void
    {
        Auth::logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }
}
