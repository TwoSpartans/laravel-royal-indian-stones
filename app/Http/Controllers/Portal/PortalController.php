<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portal\Access;
use Illuminate\Http\Request;

abstract class PortalController extends Controller
{
    public function __construct(protected Access $access) {}

    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
