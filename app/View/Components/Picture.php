<?php

namespace App\View\Components;

use App\Support\ResponsivePicture;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Picture extends Component
{
    public string $fallback;

    public ?string $avif;

    public ?string $webp;

    public function __construct(
        public string $src,
        ResponsivePicture $pictures,
        public string $alt = '',
        public ?string $width = null,
        public ?string $height = null,
        public ?string $sizes = null,
        public ?string $loading = null,
        public ?string $fetchpriority = null,
        public string $class = '',
        public string $decoding = 'async',
    ) {
        $sources = $pictures->sources($src);
        $this->fallback = $sources['fallback'];
        $this->avif = $sources['avif'];
        $this->webp = $sources['webp'];
        if ($sources['width'] !== null && $sources['height'] !== null) {
            $this->width = (string) $sources['width'];
            $this->height = (string) $sources['height'];
        }
    }

    public function render(): View
    {
        return view('components.picture');
    }
}
