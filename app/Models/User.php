<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'points',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function ownedSites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role', 'permissions')
            ->withTimestamps();
    }

    public function ownedTeams()
    {
        return $this->teams()->wherePivot('role', 'owner');
    }

    public function adminTeams()
    {
        return $this->teams()->wherePivot('role', 'admin');
    }

    public function memberTeams()
    {
        return $this->teams()->wherePivot('role', 'member');
    }

    /**
     * Vérifier si l'utilisateur est administrateur
     */
    public function isAdmin(): bool
    {
        return $this->hasPermissionTo('administrator') || $this->hasRole('admin');
    }

    /**
     * Vérifier si l'utilisateur peut gérer les catégories
     */
    public function canManageCategories(): bool
    {
        return $this->hasPermissionTo('manage categories') || $this->isAdmin();
    }

    /**
     * Vérifier si l'utilisateur peut reviewer les suggestions
     */
    public function canReviewSuggestions(): bool
    {
        return $this->hasPermissionTo('review suggestions') || $this->isAdmin();
    }

    /**
     * Vérifier si l'utilisateur peut voir les analytics
     */
    public function canViewAnalytics(): bool
    {
        return $this->hasPermissionTo('view analytics') || $this->isAdmin();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
