<?php

namespace App\Services\Portal;

class IpRange
{
    public function contains(string $ip, string $cidr): bool
    {
        $ipBinary = @inet_pton($ip);
        if ($ipBinary === false) {
            return false;
        }

        $cidr = trim($cidr);
        if (! str_contains($cidr, '/')) {
            $networkBinary = @inet_pton($cidr);

            return $networkBinary !== false && $ipBinary === $networkBinary;
        }

        [$network, $bits] = explode('/', $cidr, 2);
        $networkBinary = @inet_pton($network);
        if ($networkBinary === false || strlen($ipBinary) !== strlen($networkBinary) || ! ctype_digit($bits)) {
            return false;
        }

        $bits = (int) $bits;
        $maxBits = strlen($ipBinary) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        if (substr($ipBinary, 0, $wholeBytes) !== substr($networkBinary, 0, $wholeBytes)) {
            return false;
        }

        $remainder = $bits % 8;
        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($networkBinary[$wholeBytes]) & $mask);
    }

    public function isValidCidr(string $cidr): bool
    {
        $cidr = trim($cidr);
        if (! str_contains($cidr, '/')) {
            return filter_var($cidr, FILTER_VALIDATE_IP) !== false;
        }

        [$network, $bits] = explode('/', $cidr, 2);
        if (filter_var($network, FILTER_VALIDATE_IP) === false || ! ctype_digit($bits)) {
            return false;
        }

        $maxBits = str_contains($network, ':') ? 128 : 32;

        return (int) $bits >= 0 && (int) $bits <= $maxBits;
    }
}
