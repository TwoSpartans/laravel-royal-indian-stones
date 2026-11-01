<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['subject_type', 'subject_id', 'gap'])]
class OutreachIgnoredWarning extends Model {}
