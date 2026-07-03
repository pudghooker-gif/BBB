<?php

namespace VanguardLTE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use VanguardLTE\Support\Authorization\AuthorizationRoleTrait;

class Role extends Model
{
    use AuthorizationRoleTrait, SoftDeletes {
        AuthorizationRoleTrait::restore insteadof SoftDeletes;
        SoftDeletes::restore as restoreSoftDeleted;
    }

    protected $table = 'roles';
    protected $guarded = ['id'];
    protected $fillable = [
        'name',
        'slug',
        'description',
        'level',
    ];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'description' => 'string',
        'level' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    public $timestamps = true;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if ($connection = config('roles.connection')) {
            $this->connection = $connection;
        }

        $this->table = config('roles.rolesTable', 'roles');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function setSlugAttribute($value)
    {
        $this->attributes['slug'] = Str::slug($value, config('roles.separator', '.'));
    }

    public function hasOnePermission($permission)
    {
        return $this->permissions()->wherePivot('permission_id', $permission)->first();
    }
}
