<?php

namespace OpenSwag\Laravel\Tests\Unit\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class ArrayRulesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'price' => ['required', 'numeric'],
            'active' => ['boolean'],
            'tags' => ['array'],
            'website' => ['nullable', 'url'],
        ];
    }
}
