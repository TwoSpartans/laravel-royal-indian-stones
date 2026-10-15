<?php

namespace App\Http\Requests;

use App\Support\FormResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['email_address', 'website'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $this->merge([$key => trim($value)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_address' => ['required', 'string', 'max:254', 'email'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email_address.required' => 'Please enter a valid email address.',
            'email_address.email' => 'Please enter a valid email address.',
            'email_address.max' => 'Please enter a valid email address.',
            'website.prohibited' => 'Please leave the website field empty.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (preg_match('/[\r\n\0]/', (string) $this->input('email_address'))) {
                $validator->errors()->add('email_address', 'Please enter a valid email address.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(FormResponse::make(false, (string) $validator->errors()->first(), 422));
    }
}
