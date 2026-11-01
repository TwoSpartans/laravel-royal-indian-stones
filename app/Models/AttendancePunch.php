<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'punch_type', 'punched_at', 'timezone', 'access_event_id', 'is_correction', 'correction_id', 'superseded_at', 'note', 'working_on', 'completed_work', 'planned_work'])]
class AttendancePunch extends Model
{
    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'is_correction' => 'boolean',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function shiftNotes(): array
    {
        $labels = match ($this->punch_type) {
            'out' => [
                'working_on' => 'What are you working on',
                'completed_work' => 'What have you done',
                'planned_work' => 'What will you be doing tomorrow',
            ],
            'in' => [
                'working_on' => 'What are you working on',
                'completed_work' => 'What did you do yesterday',
                'planned_work' => 'What will you work on today',
            ],
            default => [],
        };

        $notes = [];
        foreach ($labels as $field => $label) {
            $value = trim((string) ($this->{$field} ?? ''));
            if ($value !== '') {
                $notes[] = ['label' => $label, 'value' => $value];
            }
        }

        return $notes;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
