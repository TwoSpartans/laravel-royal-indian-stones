<?php

namespace Tests\Unit;

use App\Services\Portal\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_it_matches_the_published_sha1_vector(): void
    {
        $totp = new Totp;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $totp->at($secret, 1));
        $this->assertSame('081804', $totp->at($secret, 37037036));
    }

    public function test_it_rejects_a_code_of_the_wrong_shape(): void
    {
        $totp = new Totp;

        $this->assertFalse($totp->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '12345'));
    }
}
