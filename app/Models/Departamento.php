<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Departamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'slug',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departamento');
    }
}