<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password','username',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Use an accessor to lowercase username. Because WinChatty will auth any combination
     * of username case (electroly must be lower-casing it) we need to do the same here.
     * Otherwise users will have their initial account created in whatever case but
     * subsequent (valid) WinChatty logins will fail due to case.
     * 
     * https://laravel.com/docs/5.6/eloquent-mutators
     */
    /*
    public function getUsernameAttribute($value)
    {
        return strtolower($value);
    }
    */

    /**
     *  Mike 2018-03-22:
     *  https://medium.com/@ezp127/laravel-5-4-native-user-authentication-role-authorization-3dbae4049c8a
     *  Adding user roles to application.
     * 
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     *  Check whether the user has ANY of the passed roles
     * 
     *  @param array strings rolenames
     *  @return mixed (Boolean or NULL)
     */
    public function hasAnyRole($roles)
    {
        return null !== $this->roles()->whereIn('name',$roles)->first();
    }

    /**
     *  Check whether the user has a single named role
     * 
     *  @param string role
     *  @return mixed (Boolean or NULL)
     */
    public function hasRole($role)
    {
        return null !== $this->roles()->where('name',$role)->first();
    }

    /**
     * Simple function to return lower-cased version of username for any kind of lookup/compare
     * that uses lower-case logic.
     */
    public function lowerUsername()
    {
        return strtolower($this->username);
    }

    /**
     * Quick access to all word clouds created by this user.
     */
    public function clouds()
    {
        return $this->hasMany('App\Chatty\word_cloud','user','name');
    }

}
