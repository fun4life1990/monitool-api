<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\Enums\HttpMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'method' => ['nullable', Rule::in(array_column(HttpMethod::cases(), 'value'))],
            'interval_seconds' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'timeout_ms' => ['nullable', 'integer', 'min:1000', 'max:30000'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
