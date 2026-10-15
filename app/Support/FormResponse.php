<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

class FormResponse
{
    public static function make(bool $ok, string $message, int $status = 200): Response
    {
        if (request()->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $status)
                ->header('Cache-Control', 'no-store');
        }

        return response()->view('enquiry-result', [
            'ok' => $ok,
            'message' => $message,
        ], $status)->header('Cache-Control', 'no-store');
    }
}
