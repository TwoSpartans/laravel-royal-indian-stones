<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['section', 'slot', 'path', 'alt', 'eyebrow', 'title', 'subtitle', 'body', 'button_label', 'link'])]
class HomeImage extends Model {}
