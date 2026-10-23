<?php

namespace App\Services\Portal;

use App\Models\AccessEvent;
use App\Models\RegisteredDevice;
use App\Models\User;
use App\Support\PortalTime;
use Illuminate\Http\Request;

class RecordAccess
{
    public function __construct(
        private ObserveClient $observe,
        private ClassifyNetwork $classify,
    ) {}

    public function write(Request $request, string $eventType, ?User $user, ?RegisteredDevice $device = null, ?string $attemptedEmail = null): AccessEvent
    {
        $observed = $this->observe->capture($request);
        $moment = now();
        $classification = $this->classify->classify($observed['ip'], $user, $moment);

        return AccessEvent::query()->create([
            'user_id' => $user?->id,
            'session_id' => $observed['session_id'],
            'event_type' => $eventType,
            'attempted_email' => $attemptedEmail,
            'ip_address' => $observed['ip'],
            'forwarded_for' => $observed['forwarded_for'],
            'forwarded_header_accepted' => $observed['forwarded_header_accepted'],
            'user_agent' => $observed['user_agent'],
            'classification' => $classification['result'],
            'classification_label' => $classification['label'],
            'classification_reason' => $classification['reason'],
            'office_network_id' => $classification['office_network_id'],
            'remote_work_authorization_id' => $classification['remote_work_authorization_id'],
            'registered_device_id' => $device?->id,
            'device_status' => $device?->status,
            'occurred_at' => $moment,
            'timezone' => PortalTime::timezone($user),
        ]);
    }
}
