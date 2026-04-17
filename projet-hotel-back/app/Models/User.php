<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    // use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;

    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // On ajoute automatiquement ces attributs calculés dans le JSON
    protected $appends = ['roles_list', 'permissions_list'];
    /**
     * Retourne la liste des rôles de l'utilisateur.
     */
    public function getRolesListAttribute()
    {
        return $this->roles->pluck('name');
    }

    /**
     * Retourne la liste des permissions de l'utilisateur (directes + via rôles).
     */
    public function getPermissionsListAttribute()
    {
        return $this->getAllPermissions()->pluck('name');
    }

    /**
     * Retourne l'identifiant JWT (souvent la clé primaire).
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Retourne des claims personnalisés pour le JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Relation avec les notifications
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Relation avec les notifications non lues
     */
    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->where('read', false);
    }
}
