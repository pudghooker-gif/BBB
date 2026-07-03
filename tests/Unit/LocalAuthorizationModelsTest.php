<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use VanguardLTE\Permission;
use VanguardLTE\Role;
use VanguardLTE\User;

class LocalAuthorizationModelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('users');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->integer('level')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            $table->integer('rank')->nullable();
            $table->boolean('removable')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->nullable();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->boolean('is_demo_agent')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        config([
            'roles.models.role' => Role::class,
            'roles.models.permission' => Permission::class,
            'roles.models.defaultUser' => User::class,
            'roles.rolesTable' => 'roles',
            'roles.roleUserTable' => 'role_user',
            'roles.permissionsTable' => 'permissions',
            'roles.permissionsRoleTable' => 'permission_role',
            'roles.permissionsUserTable' => 'permission_user',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('users');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        parent::tearDown();
    }

    public function testUserRoleIdFallbackResolvesLocalRoleAndPermissions()
    {
        $role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Admin role',
            'level' => 5,
        ]);

        $permission = Permission::create([
            'name' => 'Access Admin Panel',
            'slug' => 'access.admin.panel',
            'description' => 'Access backend',
            'model' => '',
        ]);

        $this->assertTrue($role->attachPermission($permission));

        $user = User::withoutEvents(function () use ($role) {
            return User::create([
                'username' => 'alice',
                'email' => 'alice@example.test',
                'password' => 'secret',
                'role_id' => $role->id,
                'is_demo_agent' => 0,
            ]);
        });

        $this->assertFalse(Schema::hasTable('role_user'));
        $this->assertInstanceOf(Role::class, $user->role);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole($role->id));
        $this->assertSame(5, $user->level());
        $this->assertTrue($role->hasPermission('access.admin.panel'));
        $this->assertTrue($user->hasPermission('access.admin.panel'));
    }

    public function testAttachRoleUpdatesRoleIdWhenRoleUserPivotIsAbsent()
    {
        $role = Role::create([
            'name' => 'User',
            'slug' => 'user',
            'description' => 'User role',
            'level' => 1,
        ]);

        $user = User::withoutEvents(function () {
            return User::create([
                'username' => 'bob',
                'email' => 'bob@example.test',
                'password' => 'secret',
                'role_id' => null,
                'is_demo_agent' => 0,
            ]);
        });

        $this->assertFalse(Schema::hasTable('role_user'));
        $attached = User::withoutEvents(function () use ($user, $role) {
            return $user->attachRole($role);
        });

        $this->assertTrue($attached);
        $this->assertSame($role->id, (int) User::withoutGlobalScopes()->find($user->id)->role_id);
    }
}
