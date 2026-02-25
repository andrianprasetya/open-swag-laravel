<?php

namespace OpenSwag\Laravel\Tests\Unit\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class FormatRulesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'website' => 'required|url',
            'birthday' => 'required|date',
            'token' => 'required|uuid',
        ];
    }
}
