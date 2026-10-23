<?php

namespace App\Services\Portal;

use App\Enums\NetworkResult;
use App\Models\OfficeNetwork;
use App\Models\RemoteWorkAuthorization;
use App\Models\User;
use App\Support\PortalTime;
use Illuminate\Support\Carbon;

class ClassifyNetwork
{
    public function __construct(private IpRange $ranges) {}

    /**
     * @return array{result: string, label: string, reason: string, office_network_id: ?int, remote_work_authorization_id: ?int}
     */
    public function classify(?string $ip, ?User $user, Carbon $moment): array
    {
        if ($ip === null) {
            return $this->result(NetworkResult::UnableToVerify, 'No usable client address was observed.', null, null);
        }

        $networks = OfficeNetwork::query()->with('location')->orderBy('id')->get();
        if ($networks->isEmpty()) {
            return $this->result(
                NetworkResult::UnableToVerify,
                'No approved office, factory, or warehouse networks have been saved.',
                null,
                null,
            );
        }

        foreach ($networks as $network) {
            if ($this->ranges->contains($ip, $network->cidr)) {
                $place = $network->location?->name ?? 'an approved site';

                return $this->result(
                    NetworkResult::ApprovedOffice,
                    'The address matches '.$network->label.' at '.$place.'. An address match does not prove the person is physically on site.',
                    $network->id,
                    null,
                );
            }
        }

        $grant = $this->remoteGrant($user, $moment);
        if ($grant !== null) {
            return $this->result(
                NetworkResult::RemoteAuthorized,
                'The address is outside the approved office ranges. Remote or field work is authorized for this date, so this is not treated as office presence.',
                null,
                $grant->id,
            );
        }

        return $this->result(
            NetworkResult::OutsideNetwork,
            'The address does not match an approved office, factory, or warehouse range.',
            null,
            null,
        );
    }

    /**
     * @return array{result: string, label: string, reason: string, office_network_id: ?int, remote_work_authorization_id: ?int}
     */
    private function result(NetworkResult $result, string $reason, ?int $networkId, ?int $grantId): array
    {
        return [
            'result' => $result->value,
            'label' => $result->label(),
            'reason' => $reason,
            'office_network_id' => $networkId,
            'remote_work_authorization_id' => $grantId,
        ];
    }

    private function remoteGrant(?User $user, Carbon $moment): ?RemoteWorkAuthorization
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('profile.location');
        $date = $moment->copy()->timezone(PortalTime::timezone($user))->toDateString();

        return RemoteWorkAuthorization::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }
}
