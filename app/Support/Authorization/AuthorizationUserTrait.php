<?php

namespace VanguardLTE\Support\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use VanguardLTE\Permission;
use VanguardLTE\Role;

trait AuthorizationUserTrait
{
    protected $rolesCache;
    protected $permissionsCache;

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, $this->rolesPivotTable())->withTimestamps();
    }

    public function getRoles()
    {
        if ($this->rolesCache) {
            return $this->rolesCache;
        }

        $roles = collect();
        if ($this->hasTable($this->rolesPivotTable())) {
            $roles = $this->roles()->get();
        }

        $directRole = null;
        $roleId = $this->getAttribute('role_id');

        if ($roleId && method_exists($this, 'role')) {
            $directRole = $this->relationLoaded('role') ? $this->getRelation('role') : $this->role()->first();
        }

        if ($directRole && !$roles->contains('id', $directRole->id)) {
            $roles->push($directRole);
        }

        return $this->rolesCache = $roles->values();
    }

    public function hasRole($role, $all = false)
    {
        if ($this->isPretendEnabled()) {
            return $this->pretend('hasRole');
        }

        return $all ? $this->hasAllRoles($role) : $this->hasOneRole($role);
    }

    public function hasOneRole($role)
    {
        foreach ($this->getArrayFrom($role) as $candidate) {
            if ($this->checkRole($candidate)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllRoles($role)
    {
        foreach ($this->getArrayFrom($role) as $candidate) {
            if (!$this->checkRole($candidate)) {
                return false;
            }
        }

        return true;
    }

    public function checkRole($role)
    {
        return $this->getRoles()->contains(function ($value) use ($role) {
            return (string) $role === (string) $value->id
                || Str::is((string) $role, (string) $value->slug)
                || Str::is((string) $role, (string) $value->name);
        });
    }

    public function attachRole($role)
    {
        $id = $this->modelKey($role);
        if (!$id) {
            return false;
        }

        if ($this->hasTable($this->rolesPivotTable()) && $this->roles()->where($this->roleTable() . '.id', $id)->exists()) {
            return true;
        }

        $this->rolesCache = null;

        if ($this->hasTable($this->rolesPivotTable())) {
            $this->roles()->attach($id);
        }

        if ($this->hasColumn($this->getTable(), 'role_id') && (string) $this->getAttribute('role_id') !== (string) $id) {
            $this->forceFill(['role_id' => $id]);

            if ($this->exists) {
                return $this->save();
            }
        }

        return true;
    }

    public function detachRole($role)
    {
        $this->rolesCache = null;

        if (!$this->hasTable($this->rolesPivotTable())) {
            return true;
        }

        return $this->roles()->detach($role);
    }

    public function detachAllRoles()
    {
        $this->rolesCache = null;

        if (!$this->hasTable($this->rolesPivotTable())) {
            return true;
        }

        return $this->roles()->detach();
    }

    public function syncRoles($roles)
    {
        $this->rolesCache = null;

        if (!$this->hasTable($this->rolesPivotTable())) {
            $firstRole = is_array($roles) ? reset($roles) : $roles;

            return $firstRole ? $this->setRole($firstRole) : true;
        }

        return $this->roles()->sync($roles);
    }

    public function level()
    {
        $role = $this->getRoles()->sortByDesc('level')->first();

        return $role ? (int) $role->level : 0;
    }

    public function hasPermission($permission, $allRequired = true)
    {
        if ($this->isPretendEnabled()) {
            return $this->pretend('hasPermission');
        }

        return $allRequired
            ? $this->hasAllPermissions($permission)
            : $this->hasOnePermission($permission);
    }

    public function hasOnePermission($permission)
    {
        foreach ($this->getArrayFrom($permission) as $candidate) {
            if ($this->checkPermission($candidate)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions($permissions)
    {
        foreach ($this->getArrayFrom($permissions) as $candidate) {
            if (!$this->checkPermission($candidate)) {
                return false;
            }
        }

        return true;
    }

    public function checkPermission($permission)
    {
        return $this->getPermissions()->contains(function ($value) use ($permission) {
            return (string) $permission === (string) $value->id
                || Str::is((string) $permission, (string) $value->slug)
                || Str::is((string) $permission, (string) $value->name);
        });
    }

    public function rolePermissions()
    {
        $permissionModel = app(config('roles.models.permission', Permission::class));
        if (!$permissionModel instanceof Model) {
            throw new InvalidArgumentException('[roles.models.permission] must be an Eloquent model.');
        }

        if (!$this->hasTable($this->permissionTable()) || !$this->hasTable($this->permissionsRolePivotTable())) {
            return $permissionModel::query()->whereRaw('1 = 0');
        }

        $roleIds = $this->getRoles()->pluck('id')->filter()->values()->toArray();

        return $permissionModel::query()
            ->select($this->permissionTable() . '.*')
            ->distinct()
            ->join($this->permissionsRolePivotTable() . ' as permission_role', 'permission_role.permission_id', '=', $this->permissionTable() . '.id')
            ->whereIn('permission_role.role_id', count($roleIds) ? $roleIds : [0]);
    }

    public function userPermissions()
    {
        return $this->belongsToMany(Permission::class, $this->permissionsUserPivotTable())->withTimestamps();
    }

    public function getPermissions()
    {
        if ($this->permissionsCache) {
            return $this->permissionsCache;
        }

        $permissions = collect();

        if ($this->hasTable($this->permissionTable()) && $this->hasTable($this->permissionsRolePivotTable())) {
            $permissions = $permissions->merge($this->rolePermissions()->get());
        }

        if ($this->hasTable($this->permissionTable()) && $this->hasTable($this->permissionsUserPivotTable())) {
            $permissions = $permissions->merge($this->userPermissions()->get());
        }

        return $this->permissionsCache = $permissions->unique('id')->values();
    }

    public function allowed($providedPermission, Model $entity, $owner = true, $ownerColumn = 'user_id')
    {
        if ($this->isPretendEnabled()) {
            return $this->pretend('allowed');
        }

        if ($owner === true && isset($entity->{$ownerColumn}) && $entity->{$ownerColumn} == $this->id) {
            return true;
        }

        foreach ($this->getPermissions() as $permission) {
            if ($permission->model !== '' && get_class($entity) === $permission->model
                && ((string) $permission->id === (string) $providedPermission || $permission->slug === $providedPermission)
            ) {
                return true;
            }
        }

        return false;
    }

    public function attachPermission($permission)
    {
        $id = $this->modelKey($permission);
        if (!$id || !$this->hasTable($this->permissionsUserPivotTable())) {
            return false;
        }

        if ($this->userPermissions()->where($this->permissionTable() . '.id', $id)->exists()) {
            return true;
        }

        $this->permissionsCache = null;

        return $this->userPermissions()->attach($permission);
    }

    public function detachPermission($permission)
    {
        $this->permissionsCache = null;

        if (!$this->hasTable($this->permissionsUserPivotTable())) {
            return true;
        }

        return $this->userPermissions()->detach($permission);
    }

    public function detachAllPermissions()
    {
        $this->permissionsCache = null;

        if (!$this->hasTable($this->permissionsUserPivotTable())) {
            return true;
        }

        return $this->userPermissions()->detach();
    }

    public function syncPermissions($permissions)
    {
        $this->permissionsCache = null;

        if (!$this->hasTable($this->permissionsUserPivotTable())) {
            return [];
        }

        return $this->userPermissions()->sync($permissions);
    }

    public function setRole($role)
    {
        $id = $this->modelKey($role);
        $this->attachRole($id);

        if (!$this->hasColumn($this->getTable(), 'role_id')) {
            return true;
        }

        return $this->forceFill([
            'role_id' => $id,
        ])->save();
    }

    private function isPretendEnabled()
    {
        return (bool) config('roles.pretend.enabled', false);
    }

    private function pretend($option)
    {
        return (bool) config('roles.pretend.options.' . $option, false);
    }

    private function getArrayFrom($argument)
    {
        return is_array($argument) ? $argument : preg_split('/ ?[,|] ?/', (string) $argument);
    }

    private function modelKey($model)
    {
        if (is_array($model)) {
            return isset($model['id']) ? $model['id'] : null;
        }

        return $model instanceof Model ? $model->getKey() : $model;
    }

    private function roleTable()
    {
        return config('roles.rolesTable', 'roles');
    }

    private function permissionTable()
    {
        return config('roles.permissionsTable', 'permissions');
    }

    private function rolesPivotTable()
    {
        return config('roles.roleUserTable', 'role_user');
    }

    private function permissionsRolePivotTable()
    {
        return config('roles.permissionsRoleTable', 'permission_role');
    }

    private function permissionsUserPivotTable()
    {
        return config('roles.permissionsUserTable', 'permission_user');
    }

    private function hasTable($table)
    {
        return Schema::hasTable($table);
    }

    private function hasColumn($table, $column)
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'is')) {
            return $this->hasRole(Str::snake(substr($method, 2), config('roles.separator', '.')));
        }

        if (Str::startsWith($method, 'can')) {
            return $this->hasPermission(Str::snake(substr($method, 3), config('roles.separator', '.')));
        }

        if (Str::startsWith($method, 'allowed')) {
            return $this->allowed(
                Str::snake(substr($method, 7), config('roles.separator', '.')),
                $parameters[0],
                isset($parameters[1]) ? $parameters[1] : true,
                isset($parameters[2]) ? $parameters[2] : 'user_id'
            );
        }

        return parent::__call($method, $parameters);
    }
}
