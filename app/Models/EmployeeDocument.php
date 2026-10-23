<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'category', 'title', 'path', 'original_name', 'expires_on', 'retention_until', 'uploaded_by', 'identity_reference'])]
class EmployeeDocument extends Model
{
    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'retention_until' => 'date',
            'identity_reference' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
