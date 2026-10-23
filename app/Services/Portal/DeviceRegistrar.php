<?php

namespace App\Services\Portal;

use App\Models\RegisteredDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class DeviceRegistrar
{
    /**
     * @return array{device: RegisteredDevice, cookie: ?Cookie}
     */
    public function sync(Request $request, User $user): array
    {
        $name = (string) config('portal.device_cookie');
        $secret = $request->cookie($name);
        $cookie = null;

        if (! is_string($secret) || preg_match('/^[a-f0-9]{64}$/', $secret) !== 1) {
            $secret = bin2hex(random_bytes(32));
            $cookie = $this->cookie($request, $secret);
        }

        $hash = hash('sha256', $secret);
        $device = RegisteredDevice::query()->where('secret_hash', $hash)->first();

        if ($device !== null && $device->user_id !== $user->id) {
            $secret = bin2hex(random_bytes(32));
            $cookie = $this->cookie($request, $secret);
            $device = null;
            $hash = hash('sha256', $secret);
        }

        if ($device === null) {
            $device = RegisteredDevice::query()->create([
                'user_id' => $user->id,
                'label' => 'Browser',
                'secret_hash' => $hash,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'status' => 'pending',
                'last_seen_at' => now(),
            ]);
        } elseif ($device->status !== 'revoked') {
            $device->forceFill([
                'last_seen_at' => now(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ])->save();
        }

        return ['device' => $device, 'cookie' => $cookie];
    }

    private function cookie(Request $request, string $secret): Cookie
    {
        return cookie(
            (string) config('portal.device_cookie'),
            $secret,
            60 * 24 * 400,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );
    }
}
