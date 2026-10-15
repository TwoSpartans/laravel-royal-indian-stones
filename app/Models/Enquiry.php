<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'subject', 'message', 'product', 'product_slug', 'quantity', 'dimensions', 'finish', 'destination'])]
class Enquiry extends Model
{
}
