<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'image', 'lightbox', 'alt', 'caption', 'srcset', 'width', 'height', 'position'])]
class Project extends Model
{
}
