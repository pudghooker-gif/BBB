<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorPlayer;
use VanguardLTE\Role;
use VanguardLTE\User;

class ShadowUserManager
{
    public function ensureShadowUser(B2BOperator $operator, B2BOperatorPlayer $player)
    {
        if (!Schema::hasTable('users')) {
            throw new \RuntimeException('users table does not exist');
        }

        if ($player->shadow_user_id) {
            $existing = User::withoutGlobalScopes()->where('id', $player->shadow_user_id)->first();
            if ($existing) {
                $this->syncExistingShadowUser($existing, $operator, $player);
                return $existing;
            }
        }

        $username = $this->makeUsername($operator, $player);
        $existing = User::withoutGlobalScopes()->where('username', $username)->first();
        if ($existing) {
            $player->update(['shadow_user_id' => $existing->id]);
            $this->syncExistingShadowUser($existing, $operator, $player);
            return $existing;
        }

        $columns = Schema::getColumnListing('users');
        $now = now();
        $apiToken = $this->makeApiToken();

        $data = [];
        $this->put($data, $columns, 'username', $username);
        $this->put($data, $columns, 'email', $username . '@b2b.local');
        $this->put($data, $columns, 'password', Hash::make(Str::random(32)));
        $this->put($data, $columns, 'api_token', $apiToken);
        $this->put($data, $columns, 'auth_token', Str::random(64));
        $this->put($data, $columns, 'remember_token', Str::random(10));
        $this->put($data, $columns, 'role_id', 1);
        $this->put($data, $columns, 'status', $this->activeStatusValue());
        $this->put($data, $columns, 'shop_id', $operator->shop_id ?: 0);
        $this->put($data, $columns, 'parent_id', $this->resolveParentId($operator));
        $this->put($data, $columns, 'balance', 0);
        $this->put($data, $columns, 'count_balance', 0);
        $this->put($data, $columns, 'currency', strtoupper($player->currency ?: 'USD'));
        $this->put($data, $columns, 'country', strtoupper((string) $player->country));
        $this->put($data, $columns, 'language', $player->language ?: 'en');
        $this->put($data, $columns, 'is_blocked', 0);
        $this->put($data, $columns, 'phone_verified', 1);
        $this->put($data, $columns, 'is_demo_agent', 0);
        $this->put($data, $columns, 'created_at', $now);
        $this->put($data, $columns, 'updated_at', $now);

        $id = DB::table('users')->insertGetId($data);
        $user = User::withoutGlobalScopes()->where('id', $id)->first();

        if (!$user) {
            throw new \RuntimeException('Shadow user was inserted but could not be loaded');
        }

        $this->attachUserRoleIfPossible($user);
        $player->update(['shadow_user_id' => $user->id]);

        return $user;
    }

    public function refreshApiToken($user)
    {
        if (!Schema::hasColumn('users', 'api_token')) {
            throw new \RuntimeException('users.api_token column does not exist; legacy launcher cannot be used');
        }

        $token = $this->makeApiToken();
        $data = ['api_token' => $token];
        if (Schema::hasColumn('users', 'updated_at')) {
            $data['updated_at'] = now();
        }
        DB::table('users')->where('id', $user->id)->update($data);

        $user->api_token = $token;

        return $token;
    }

    private function syncExistingShadowUser($user, B2BOperator $operator, B2BOperatorPlayer $player)
    {
        $columns = Schema::getColumnListing('users');
        $data = [];

        $this->put($data, $columns, 'shop_id', $operator->shop_id ?: 0);
        $this->put($data, $columns, 'currency', strtoupper($player->currency ?: 'USD'));
        $this->put($data, $columns, 'country', strtoupper((string) $player->country));
        $this->put($data, $columns, 'language', $player->language ?: 'en');
        $this->put($data, $columns, 'status', $this->activeStatusValue());
        $this->put($data, $columns, 'is_blocked', 0);
        $this->put($data, $columns, 'updated_at', now());

        if (count($data)) {
            DB::table('users')->where('id', $user->id)->update($data);
        }
    }

    private function put(array &$data, array $columns, $column, $value)
    {
        if (in_array($column, $columns, true)) {
            $data[$column] = $value;
        }
    }

    private function makeUsername(B2BOperator $operator, B2BOperatorPlayer $player)
    {
        $hash = substr(sha1($operator->id . ':' . $player->external_player_id), 0, 16);
        return 'b2b_op' . $operator->id . '_' . $hash;
    }

    private function makeApiToken()
    {
        return 'b2b_' . Str::random(60);
    }

    private function activeStatusValue()
    {
        $class = '\\VanguardLTE\\Support\\Enum\\UserStatus';
        if (class_exists($class) && defined($class . '::ACTIVE')) {
            return constant($class . '::ACTIVE');
        }

        return 'Active';
    }

    private function resolveParentId(B2BOperator $operator)
    {
        $metadata = is_array($operator->metadata) ? $operator->metadata : [];
        if (isset($metadata['shadow_parent_id']) && $metadata['shadow_parent_id']) {
            return (int) $metadata['shadow_parent_id'];
        }

        return 0;
    }

    private function attachUserRoleIfPossible($user)
    {
        try {
            if (!method_exists($user, 'attachRole')) {
                return;
            }

            $role = Role::where('name', 'User')
                ->orWhere('slug', 'user')
                ->first();

            if ($role) {
                $user->attachRole($role);
            }
        } catch (\Exception $e) {
            // Some installs use only role_id. Do not block launch.
        }
    }
}
