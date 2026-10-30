<?php

namespace App\Models;

use App\Enums\OutreachMentionStatus;
use Database\Factories\OutreachMentionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['publication', 'contact_name', 'offered_url', 'status', 'published_url', 'note'])]
class OutreachMention extends Model
{
    /** @use HasFactory<OutreachMentionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OutreachMentionStatus::class,
        ];
    }
}
