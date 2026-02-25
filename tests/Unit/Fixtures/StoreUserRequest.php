<?php

namespace OpenSwag\Laravel\Tests\Unit\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|string|email',
            'age' => 'required|integer',
            'bio' => 'nullable|string',
            'role' => 'required|string|in:admin,editor,viewer',
        ];
    }
}
