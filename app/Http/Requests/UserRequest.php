<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = auth()->user()->id;
        return match ($this->method()) {
            'PATCH' => [
                "name" => ["string", "max:255"],
                "username" => ["string", Rule::unique('users', 'username')->ignore($userId)],
                "email" => ["string","email","max:255",Rule::unique('users', 'email')->ignore($userId)]
            ],
            default => [],
        };
    }
}
