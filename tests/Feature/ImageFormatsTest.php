<?php

namespace Tests\Feature;

use App\Support\ResponsivePicture;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImageFormatsTest extends TestCase
{
    public function test_a_photograph_offers_avif_then_webp_then_the_original_jpeg(): void
    {
        $relative = 'assets/img/optimized/picture-order-fixture.jpg';
        $absolute = public_path($relative);
        File::ensureDirectoryExists(dirname($absolute));
        $image = imagecreatetruecolor(200, 140);
        imagejpeg($image, $absolute, 80);
        unset($image);

        try {
            $this->assertTrue(app(ResponsivePicture::class)->ensure($relative));

            $html = Blade::render('<x-picture src="'.$relative.'" alt="Stone sample" width="200" height="140" />');
            $avif = strpos($html, 'type="image/avif"');
            $webp = strpos($html, 'type="image/webp"');

            $this->assertNotFalse($avif);
            $this->assertNotFalse($webp);
            $this->assertLessThan($webp, $avif);
            $this->assertMatchesRegularExpression('/<img[^>]*src="[^"]*picture-order-fixture\.jpg"/', $html);
        } finally {
            File::delete($absolute);
            foreach (glob(public_path('assets/img/optimized/'.substr(md5($relative), 0, 16).'-*')) ?: [] as $variant) {
                File::delete($variant);
            }
        }
    }
}
