<?php

namespace App\Models;

use App\Support\ServedUpload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'description', 'version_label', 'cover_path', 'pdf_path', 'pdf_original_name', 'pdf_bytes', 'position'])]
class Catalogue extends Model
{
    public function pdfFileExists(): bool
    {
        if (! is_string($this->pdf_path) || $this->pdf_path === '') {
            return false;
        }

        $absolute = app(ServedUpload::class)->absolute($this->pdf_path);

        return is_string($absolute) && is_file($absolute);
    }

    public function pdfSizeLabel(): ?string
    {
        $bytes = $this->pdf_bytes;
        if ($bytes === null) {
            return null;
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024).' KB';
        }

        return number_format($bytes / 1048576, 1).' MB';
    }

    protected function casts(): array
    {
        return [
            'pdf_bytes' => 'integer',
            'position' => 'integer',
        ];
    }
}
