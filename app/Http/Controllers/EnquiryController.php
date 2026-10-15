<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\Product;
use App\Support\FormResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class EnquiryController extends Controller
{
    public function __invoke(StoreEnquiryRequest $request): Response
    {
        $key = 'enquiry:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return FormResponse::make(false, 'Please wait 30 seconds before sending another request.', 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        $data = $request->safe()->only([
            'name', 'email', 'subject', 'message', 'product', 'quantity', 'dimensions', 'finish', 'destination',
        ]);
        $productName = $data['product'] ?? '';
        $product = $productName !== ''
            ? Product::query()->where('name', $productName)->orWhere('slug', $productName)->first()
            : null;

        $enquiry = Enquiry::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'product' => $productName !== '' ? $productName : null,
            'product_slug' => $product?->slug,
            'quantity' => ($data['quantity'] ?? '') !== '' ? $data['quantity'] : null,
            'dimensions' => ($data['dimensions'] ?? '') !== '' ? $data['dimensions'] : null,
            'finish' => ($data['finish'] ?? '') !== '' ? $data['finish'] : null,
            'destination' => ($data['destination'] ?? '') !== '' ? $data['destination'] : null,
        ]);

        RateLimiter::hit($key, 30);

        try {
            Mail::to((string) config('mail.enquiry_address'))->send(new EnquiryReceived($enquiry));
        } catch (Throwable $exception) {
            report($exception);
        }

        return FormResponse::make(true, 'Your enquiry has been received. Thank you for sharing your requirements.');
    }
}
