<?php

namespace App\Services\Portal;

use Illuminate\Http\Request;

class ObserveClient
{
    public function __construct(private IpRange $ranges) {}

    /**
     * @return array{ip: ?string, forwarded_for: ?string, forwarded_header_accepted: bool, user_agent: ?string, session_id: ?string}
     */
    public function capture(Request $request): array
    {
        $remote = $request->server('REMOTE_ADDR');
        $remote = is_string($remote) ? trim($remote) : '';
        $forwarded = $request->headers->get('X-Forwarded-For');
        $forwarded = is_string($forwarded) ? trim($forwarded) : '';
        $ip = $this->validIp($remote);
        $accepted = false;

        if ($forwarded !== '' && $ip !== null && $this->peerIsTrusted($ip)) {
            $resolved = $this->clientFromForwarded($forwarded);
            if ($resolved !== null) {
                $ip = $resolved;
                $accepted = true;
            }
        }

        $agent = $request->userAgent();

        return [
            'ip' => $ip,
            'forwarded_for' => $forwarded === '' ? null : mb_substr($forwarded, 0, 1000),
            'forwarded_header_accepted' => $accepted,
            'user_agent' => is_string($agent) && $agent !== '' ? mb_substr($agent, 0, 500) : null,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
        ];
    }

    /**
     * @return list<string>
     */
    public function trustedProxies(): array
    {
        $configured = config('portal.trusted_proxies');
        if (! is_string($configured) || trim($configured) === '' || trim($configured) === '*') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $configured))));
    }

    private function peerIsTrusted(string $ip): bool
    {
        foreach ($this->trustedProxies() as $proxy) {
            if ($this->ranges->contains($ip, $proxy)) {
                return true;
            }
        }

        return false;
    }

    private function clientFromForwarded(string $header): ?string
    {
        $hops = array_reverse(array_values(array_filter(array_map(trim(...), explode(',', $header)))));
        foreach ($hops as $hop) {
            if ($this->validIp($hop) === null) {
                continue;
            }
            if ($this->peerIsTrusted($hop)) {
                continue;
            }

            return $hop;
        }

        $first = trim(explode(',', $header)[0] ?? '');

        return $this->validIp($first);
    }

    private function validIp(string $ip): ?string
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }
}
