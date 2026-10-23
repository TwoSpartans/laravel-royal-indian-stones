<?php

namespace App\Services\Portal;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditWriter
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function write(?User $actor, string $action, ?Model $subject, array $properties, ?string $ip): void
    {
        AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => $ip,
        ]);
    }
}
