<?php

namespace App\Support;

final class OutreachPageAddress
{
    /**
     * A path on this site, or an http(s) address.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:255', 'regex:/^(?:\/(?!\/)\S*|https?:\/\/\S+)$/'];
    }

    public static function message(): string
    {
        return 'Enter a page on this site, starting with /, or a full http address.';
    }
}
