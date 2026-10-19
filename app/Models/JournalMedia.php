<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['path', 'alt', 'width', 'height'])]
class JournalMedia extends Model
{
    protected $table = 'journal_media';
}
