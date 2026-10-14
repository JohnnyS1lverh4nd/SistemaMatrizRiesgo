<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Si no se envía slug, se genera a partir del nombre ("Formación Profesional"
 * -> "formacion-profesional"). La unicidad se valida igual que si se enviara.
 */
trait GeneraSlug
{
    /** Formato kebab-case en minúsculas, sin acentos ni espacios. */
    protected const FORMATO_SLUG = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && is_string($this->input('nombre'))) {
            $this->merge(['slug' => Str::slug($this->input('nombre'))]);
        }
    }
}
