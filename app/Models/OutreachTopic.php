<?php

namespace App\Models;

use App\Enums\OutreachTopicIntent;
use Database\Factories\OutreachTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phrase', 'intent', 'target_url', 'country', 'note'])]
class OutreachTopic extends Model
{
    /** @use HasFactory<OutreachTopicFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'intent' => OutreachTopicIntent::class,
        ];
    }
}
