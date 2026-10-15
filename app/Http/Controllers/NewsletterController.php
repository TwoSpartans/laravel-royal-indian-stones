<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsletterRequest;
use App\Models\NewsletterSubscriber;
use App\Support\FormResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\RateLimiter;

class NewsletterController extends Controller
{
    public function __invoke(StoreNewsletterRequest $request): Response
    {
        $key = 'newsletter:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return FormResponse::make(false, 'Please wait 30 seconds before sending another request.', 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        $email = strtolower((string) $request->validated('email_address'));
        NewsletterSubscriber::query()->firstOrCreate(['email' => $email]);
        RateLimiter::hit($key, 30);

        return FormResponse::make(true, 'Thank you. Your newsletter subscription has been received.');
    }
}
