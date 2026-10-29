<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'eyebrow', 'heading', 'body', 'button_label'])]
class HomeSection extends Model {}
