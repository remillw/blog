<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_path',
        'website',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($team) {
            if (empty($team->slug)) {
                $team->slug = Str::slug($team->name);
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'permissions')
            ->withTimestamps();
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class)
            ->withPivot('default_permissions', 'is_active')
            ->withTimestamps();
    }

    public function owners()
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    public function admins()
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    public function members()
    {
        return $this->users()->wherePivot('role', 'member');
    }
}
