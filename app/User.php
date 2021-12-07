<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string email
 * @property string name
 * @property string password
 * @property string remember_token
 * @property string two_factor_recovery_codes
 * @property string two_factor_secret
 */
class User extends Authenticatable
{
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return bool
     */
    public function twoFactorAuthEnabled()
    {
        return !is_null($this->two_factor_secret);
    }

    public function scopeWithoutBotUser(Builder $query)
    {
        return $query->where('email', '<>', config('sanctum.bot_user'));
    }
}
