<?php

namespace App\Models;

use App\Enums\OutreachProfileKind;
use Database\Factories\OutreachProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'url', 'kind', 'last_checked_on', 'gap'])]
class OutreachProfile extends Model
{
    /** @use HasFactory<OutreachProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => OutreachProfileKind::class,
            'last_checked_on' => 'date',
        ];
    }
}
