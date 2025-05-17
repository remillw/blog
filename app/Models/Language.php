<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'flag_code',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function getFlagUrlAttribute(): string
    {
        return "https://flagcdn.com/{$this->flag_code}.svg";
    }
}
