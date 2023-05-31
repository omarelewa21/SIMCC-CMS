<?php

namespace App\Models;

use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable 
{
    use Notifiable, HasFactory, HasApiTokens, Filterable;

    private static $whiteListFilter = [
        'username',
        'name',
        'email',
        'status',
        'role_id',
        'school_id',
        'country_id',
        'organization_id'
    ];

    protected $table = 'users';

    protected $fillable = [
        'country_id',
        'school_id',
        'name',
        'username',
        'email',
        'phone',
        'about',
        'organization_id',
        'role_id',
        'password',
        'useractive',
        'loginattempts',
        'status'
    ];

    protected $appends = ['organization_name','country_name','school_name','private_school','role_name','created_by','Last_modified_by'];

    protected $hidden = [
        'password',
        'loginattempts',
        'email_verified_at'
    ];

    public function created_by ()
    {
        return $this->belongsTo(User::class,"created_by_userid","id");
    }

    public function modified_by ()
    {
        return $this->belongsTo(User::class,"last_modified_userid","id");
    }

    public function roles () {
        return $this->hasMany(Roles::class, "id", 'role_id');
    }

    public function role ()
    {
        return $this->hasOne(Roles::class, 'id', 'role_id');
    }

    public function organization () {
        return $this->belongsTo(Organization::class,"organization_id",'id');
    }

    public function country () {
        return $this->hasOne(Countries::class,"id",'country_id');
    }

    public function school () {
        return $this->hasOne(School::class,"id",'school_id');
    }

    public function tasks () {
        return $this->hasMany(Tasks::class,"created_by_userid","id");
    }

    public function competitionOrganization () {
        return $this->hasMany(CompetitionOrganization::class,"organization_id","id");
    }

    public function collection () {
        return $this->hasMany(Collections::class,"created_by_userid","id");
    }

    public function getOrganizationNameAttribute () {
        return $this->organization()->first()->name ?? null;
    }

    public function getCountryNameAttribute () {
        return $this->country()->first()->display_name ?? null;
    }

    public function getSchoolNameAttribute () {
        return $this->school()->first()->name ?? null;
    }

    public function getPrivateSchoolAttribute () {
        return $this->school()->first()->private ?? null;
    }

    public function getRoleNameAttribute () {
        return $this->roles()->first()->name ?? null;
    }

    /**
     * set created by attribute
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function createdBy(): Attribute
    {
        return Attribute::make(
            get: function($value, $attributes){
                if (array_key_exists('created_by_userid', $attributes) && !is_null($attributes['created_by_userid'])){
                    return sprintf(
                        "%s %s", 
                        User::whereId($attributes['created_by_userid'])->value('username'),
                        !is_null($attributes['created_at']) ? $attributes['created_at'] : '-'
                    );
                }
                return '-';
            }
        );
    }

    /**
     * set last modified by attribute
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function lastModifiedBy(): Attribute
    {
        return Attribute::make(
            get: function($value, $attributes){
                if (array_key_exists('last_modified_userid', $attributes) && !is_null($attributes['last_modified_userid'])){
                    return sprintf(
                        "%s %s", 
                        User::whereId($attributes['last_modified_userid'])->value('username'),
                        !is_null($attributes['updated_at']) ? $attributes['updated_at'] : '-'
                    );
                }
                return '-';
            }
        );
    }

    public function getLastModifiedByAttribute() {
        if (isset($this->last_modified_userid)){
            $username = User::find($this->last_modified_userid)->username;
            return $username . ' ' .$this->updated_at;
        }
        return '-';
    }

    /**
     * Check if authinticated user has given role
     * 
     * @param string|array $role
     * @return bool
     */
    public function hasRole(string|array $roles): bool
    {
        if(is_array($roles)){
            return !is_null(collect($roles)->first(fn($value) =>
                str::lower(auth()->user()->role()->value('name')) === str::lower($value)
            ));
        }

        return str::lower(auth()->user()->role()->value('name')) === str::lower($roles);
    }
}
