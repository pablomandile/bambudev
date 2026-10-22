<?php

namespace App\Http\Requests\Admin;

use App\Models\MicroApp;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MicroAppRequest extends FormRequest
{
    public const ICON_PATTERN = '/^ph-[a-z0-9-]+$/';

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => trim((string) $this->input('description')) ?: null,
            'target' => trim((string) $this->input('target')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'icon' => ['required', 'string', 'max:64', 'regex:'.self::ICON_PATTERN],
            'hue' => ['required', 'integer', 'between:0,360'],
            'tags' => ['nullable', 'string', 'max:255'],
            'target_type' => ['required', Rule::in([MicroApp::TARGET_INTERNAL, MicroApp::TARGET_EXTERNAL])],
            'target' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value === null) {
                        return;
                    }
                    $external = $this->input('target_type') === MicroApp::TARGET_EXTERNAL;

                    if ($external && ! (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value))) {
                        $fail('El destino externo tiene que ser una URL completa, por ejemplo https://agendaflex.com.ar.');
                    }

                    if (! $external && ! preg_match('#^/(?!/)#', $value)) {
                        $fail('El destino interno tiene que ser una ruta que empiece con «/», por ejemplo /apps/billetera.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'category_id' => 'categoría',
            'icon' => 'icono',
            'hue' => 'color',
            'target_type' => 'tipo de destino',
            'target' => 'destino',
        ];
    }
}
