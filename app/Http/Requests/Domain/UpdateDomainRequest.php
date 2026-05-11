<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\Enums\HttpMethod;
use App\Models\Domain;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDomainRequest extends FormRequest
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
        $userId = $this->user()?->id;
        $domain = $this->route('domain');
        $domainId = $domain instanceof Domain ? $domain->id : null;

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'url' => [
                'sometimes', 'required', 'string', 'url:http,https', 'max:2048',
                $this->uniquePerUserRule($userId, $domainId),
            ],
            'method' => ['sometimes', 'required', Rule::in(array_column(HttpMethod::cases(), 'value'))],
            'interval_seconds' => ['sometimes', 'required', 'integer', 'min:60', 'max:86400'],
            'timeout_ms' => ['sometimes', 'required', 'integer', 'min:1000', 'max:30000'],
            'notify_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function uniquePerUserRule(?int $userId, ?int $ignoreId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($userId, $ignoreId): void {
            if ($userId === null || ! is_string($value)) {
                return;
            }

            $exists = Domain::query()
                ->where('user_id', $userId)
                ->where('url_normalized', Domain::normalizeUrl($value))
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $fail('You are already monitoring this URL.');
            }
        };
    }
}
