<?php

namespace VanguardLTE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Permission extends Model
{
    use SoftDeletes;

    protected $table = 'permissions';
    protected $guarded = ['id'];
    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'description',
        'model',
        'group_id',
        'rank',
        'removable',
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
        'model' => 'string',
        'removable' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if ($connection = config('roles.connection')) {
            $this->connection = $connection;
        }

        $this->table = config('roles.permissionsTable', 'permissions');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, config('roles.permissionsRoleTable', 'permission_role'))->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, config('roles.permissionsUserTable', 'permission_user'))->withTimestamps();
    }

    public function setSlugAttribute($value)
    {
        $this->attributes['slug'] = Str::slug($value, config('roles.separator', '.'));
    }
}
