<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Package Connection
    |--------------------------------------------------------------------------
    |
    | You can set a different database connection for this package. It will set
    | new connection for models Role and Permission. When this option is null,
    | it will connect to the main database, which is set up in database.php
    |
    */

    'connection' => null,

    'rolesTable' => env('ROLES_DATABASE_TABLE', 'roles'),

    'roleUserTable' => env('ROLES_ROLE_USER_DATABASE_TABLE', 'role_user'),

    'permissionsTable' => env('ROLES_PERMISSIONS_DATABASE_TABLE', 'permissions'),

    'permissionsRoleTable' => env('ROLES_PERMISSION_ROLE_DATABASE_TABLE', 'permission_role'),

    'permissionsUserTable' => env('ROLES_PERMISSION_USER_DATABASE_TABLE', 'permission_user'),

    /*
    |--------------------------------------------------------------------------
    | Slug Separator
    |--------------------------------------------------------------------------
    |
    | Here you can change the slug separator. This is very important in matter
    | of magic method __call() and also a `Slugable` trait. The default value
    | is a dot.
    |
    */

    'separator' => '.',

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The application uses local role and permission models here so runtime
    | authorization can evolve independently from the legacy roles package.
    |
    */

    'models' => [
        'role' => VanguardLTE\Role::class,
        'permission' => VanguardLTE\Permission::class,
        'defaultUser' => env('ROLES_DEFAULT_USER_MODEL', VanguardLTE\User::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles, Permissions and Allowed "Pretend"
    |--------------------------------------------------------------------------
    |
    | You can pretend or simulate package behavior no matter what is in your
    | database. It is really useful when you are testing you application.
    | Set up what will methods hasRole(), hasPermission() and allowed() return.
    |
    */

    'pretend' => [

        'enabled' => false,

        'options' => [
            'hasRole' => true,
            'hasPermission' => true,
            'allowed' => true,
        ],

    ],

];
