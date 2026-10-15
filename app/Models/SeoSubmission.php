<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'site_name', 'url', 'status', 'note'])]
class SeoSubmission extends Model
{
    public const TYPES = [
        'article' => 'Article',
        'guest_post' => 'Guest post',
        'social' => 'Social media',
        'global_social' => 'Global social media',
        'directory' => 'Directory',
        'infographic' => 'Infographic',
        'ppt_pdf' => 'PPT / PDF',
        'image' => 'Image',
        'classified' => 'Classified',
    ];
}
