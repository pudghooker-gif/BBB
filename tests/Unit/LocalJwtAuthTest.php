<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class LocalJwtAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->nullable();
            $table->string('password');
            $table->boolean('is_demo_agent')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->unsignedInteger('user_id')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        config([
            'auth.defaults.guard' => 'api',
            'jwt.secret' => 'local-jwt-test-secret',
            'jwt.ttl' => 60,
            'jwt.lottery' => [0, 100],
        ]);

        app('tymon.jwt.auth')->setRequest(Request::create('/api/login', 'POST'));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function testLocalJwtCanLoginAuthenticateThroughGuardAndRevokeToken()
    {
        $userId = $this->createUser('alice', 'CorrectHorseBattery12');
        $jwt = app('tymon.jwt.auth');

        $token = $jwt->attempt([
            'username' => 'alice',
            'password' => 'CorrectHorseBattery12',
        ]);

        $this->assertIsString($token);
        $tokenId = $jwt->setToken($token)->getClaim('jti');
        $this->assertTrue(DB::table('api_tokens')->where('id', $tokenId)->where('user_id', $userId)->exists());

        $request = Request::create('/api/profile', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        Auth::guard('api')->setRequest($request);

        $this->assertTrue(Auth::guard('api')->check());
        $this->assertSame($userId, Auth::guard('api')->id());

        app('tymon.jwt.auth')->setToken($token)->invalidate();

        Auth::guard('api')->setRequest($request);

        $this->assertFalse(DB::table('api_tokens')->where('id', $tokenId)->exists());
        $this->assertFalse(Auth::guard('api')->check());
    }

    public function testInvalidJwtSignatureIsRejected()
    {
        $this->createUser('bob', 'CorrectHorseBattery12');

        $token = app('tymon.jwt.auth')->attempt([
            'username' => 'bob',
            'password' => 'CorrectHorseBattery12',
        ]);

        $this->expectException(TokenInvalidException::class);

        app('tymon.jwt.auth')->setToken($token . 'tampered')->getPayload();
    }

    private function createUser($username, $password)
    {
        return DB::table('users')->insertGetId([
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => Hash::make($password),
            'is_demo_agent' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
