<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'session_id',
    'event_type',
    'attempted_email',
    'ip_address',
    'forwarded_for',
    'forwarded_header_accepted',
    'user_agent',
    'classification',
    'classification_label',
    'classification_reason',
    'office_network_id',
    'remote_work_authorization_id',
    'registered_device_id',
    'device_status',
    'occurred_at',
    'timezone',
    'reviewed_by',
    'reviewed_at',
    'review_decision',
    'review_note',
])]
class AccessEvent extends Model
{
    protected function casts(): array
    {
        return [
            'forwarded_header_accepted' => 'boolean',
            'occurred_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RegisteredDevice::class, 'registered_device_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(OfficeNetwork::class, 'office_network_id');
    }
}
