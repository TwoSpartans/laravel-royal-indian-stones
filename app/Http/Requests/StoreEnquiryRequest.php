<?php

namespace App\Http\Requests;

use App\Support\FormResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];
        foreach (['name', 'email', 'subject', 'message', 'product', 'quantity', 'dimensions', 'finish', 'destination', 'website'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $trimmed[$key] = trim($value);
            }
        }
        $this->merge($trimmed);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'max:254'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:8000'],
            'product' => ['nullable', 'string', 'max:180'],
            'quantity' => ['nullable', 'string', 'max:100'],
            'dimensions' => ['nullable', 'string', 'max:120'],
            'finish' => ['nullable', 'string', 'max:120'],
            'destination' => ['nullable', 'string', 'max:100'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please check the name field.',
            'name.max' => 'Please check the name field.',
            'email.required' => 'Please check the email field.',
            'email.max' => 'Please check the email field.',
            'subject.required' => 'Please check the subject field.',
            'subject.max' => 'Please check the subject field.',
            'message.required' => 'Please check the message field.',
            'message.min' => 'Please describe your requirements in at least 10 characters.',
            'message.max' => 'Please check the message field.',
            'product.max' => 'Please check the product field.',
            'quantity.max' => 'Please check the quantity field.',
            'dimensions.max' => 'Please check the dimensions field.',
            'finish.max' => 'Please check the finish field.',
            'destination.max' => 'Please check the destination field.',
            'website.prohibited' => 'Please leave the website field empty.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = (string) $this->input('name');
            $email = (string) $this->input('email');
            $subject = (string) $this->input('subject');
            if ($name !== '' && $email !== '' && $subject !== '' && (preg_match('/[\r\n\0]/', $email.$subject.$name) || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $validator->errors()->add('email', 'Please enter a valid email address and a single-line subject.');
            }
            foreach (['product', 'quantity', 'dimensions', 'finish', 'destination'] as $key) {
                if (preg_match('/[\r\n\0]/', (string) $this->input($key))) {
                    $validator->errors()->add($key, 'Please use a single line for the '.$key.' field.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(FormResponse::make(false, (string) $validator->errors()->first(), 422));
    }
}
