<?php

namespace App\Http\Requests\Departamentos;

use App\Http\Requests\Concerns\GeneraSlug;
use App\Models\Departamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartamentoRequest extends FormRequest
{
    use GeneraSlug;

    public function authorize(): bool
    {
        return $this->user()->can('create', Departamento::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', self::FORMATO_SLUG, Rule::unique('departamentos', 'slug')],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ];
    }
}
