<?php

namespace VanguardLTE\Support\Authorization;

use Cache;
use Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use VanguardLTE\Permission;

trait AuthorizationRoleTrait
{
    /**
     * Get cached permissions for this role.
     * @return mixed
     */
    public function cachedPermissions()
    {
        if (!$this->authorizationTableExists($this->permissionTable()) || !$this->authorizationTableExists($this->permissionsRolePivotTable())) {
            return collect();
        }

        return Cache::remember($this->getCacheKey(), Config::get('cache.ttl'), function () {
            return $this->permissions()->get();
        });
    }

    /**
     * Override "save" role method to clear role cache.
     * @param array $options
     */
    public function save(array $options = [])
    {
        $this->flushCache();

        return parent::save($options);
    }

    /**
     * Override "delete" role method to clear role cache.
     * @param array $options
     */
    public function delete(array $options = [])
    {
        $this->flushCache();

        return parent::delete();
    }

    /**
     * Override "restore" role method to clear role cache.
     */
    public function restore()
    {
        $this->flushCache();

        if (method_exists($this, 'restoreSoftDeleted')) {
            return $this->restoreSoftDeleted();
        }

        return parent::restore();
    }

    /**
     * Many-to-Many relations with the permission model.
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, $this->permissionsRolePivotTable(), 'role_id', 'permission_id')->withTimestamps();
    }

    /**
     * Checks if the role has a permission by its name.
     *
     * @param string $name Permission name.
     * @return bool
     */
    public function hasPermission($name)
    {
        return $this->cachedPermissions()->contains(function ($permission) use ($name) {
            return (string) $name === (string) $permission->id
                || Str::is((string) $name, (string) $permission->slug)
                || Str::is((string) $name, (string) $permission->name);
        });
    }

    /**
     * Save the inputted permissions.
     *
     * @param mixed $inputPermissions
     *
     * @return void
     */
    public function savePermissions($inputPermissions)
    {
        if (!$this->authorizationTableExists($this->permissionsRolePivotTable())) {
            return [];
        }

        if (! empty($inputPermissions)) {
            $this->permissions()->sync($inputPermissions);
        } else {
            $this->permissions()->detach();
        }

        $this->flushCache();
    }

    /**
     * Attach permission to current role.
     *
     * @param object|array $permission
     *
     * @return void
     */
    public function attachPermission($permission)
    {
        if (!$this->authorizationTableExists($this->permissionsRolePivotTable())) {
            return false;
        }

        $permission = $this->modelKey($permission);
        if (!$permission) {
            return false;
        }

        if (!$this->permissions()->where($this->permissionTable() . '.id', $permission)->exists()) {
            $this->permissions()->attach($permission);
        }

        $this->flushCache();

        return true;
    }

    /**
     * Detach permission from current role.
     *
     * @param object|array $permission
     *
     * @return void
     */
    public function detachPermission($permission)
    {
        if (!$this->authorizationTableExists($this->permissionsRolePivotTable())) {
            return true;
        }

        $permission = $this->modelKey($permission);
        $this->permissions()->detach($permission);

        $this->flushCache();

        return true;
    }

    public function detachAllPermissions()
    {
        if (!$this->authorizationTableExists($this->permissionsRolePivotTable())) {
            return true;
        }

        $this->permissions()->detach();

        $this->flushCache();

        return true;
    }

    /**
     * Attach multiple permissions to current role.
     *
     * @param mixed $permissions
     *
     * @return void
     */
    public function attachPermissions($permissions)
    {
        foreach ($permissions as $permission) {
            $this->attachPermission($permission);
        }
    }

    /**
     * Detach multiple permissions from current role
     *
     * @param mixed $permissions
     *
     * @return void
     */
    public function detachPermissions($permissions)
    {
        foreach ($permissions as $permission) {
            $this->detachPermission($permission);
        }
    }

    /**
     * Sync role permissions.
     * @param $permissions array Permission IDs.
     */
    public function syncPermissions(array $permissions)
    {
        if (!$this->authorizationTableExists($this->permissionsRolePivotTable())) {
            return [];
        }

        $this->permissions()->sync($permissions);

        $this->flushCache();
    }

    /**
     * Get permissions cache key.
     * @return string
     */
    private function getCacheKey()
    {
        return 'entrust_permissions_for_role_'.$this->{$this->primaryKey};
    }

    /**
     * Flush cached permissions for this role.
     */
    private function flushCache()
    {
        Cache::forget($this->getCacheKey());
    }

    private function modelKey($model)
    {
        if (is_array($model)) {
            return isset($model['id']) ? $model['id'] : null;
        }

        return $model instanceof Model ? $model->getKey() : $model;
    }

    private function permissionTable()
    {
        return config('roles.permissionsTable', 'permissions');
    }

    private function permissionsRolePivotTable()
    {
        return config('roles.permissionsRoleTable', 'permission_role');
    }

    private function authorizationTableExists($table)
    {
        return Schema::hasTable($table);
    }
}
