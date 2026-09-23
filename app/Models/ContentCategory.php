<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(ContentIdea::class, 'category_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(ContentProject::class, 'category_id');
    }
}
