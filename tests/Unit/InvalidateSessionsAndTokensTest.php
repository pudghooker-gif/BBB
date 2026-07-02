<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use VanguardLTE\Events\User\UserCredentialsChanged;
use VanguardLTE\Listeners\Users\InvalidateSessionsAndTokens;
use VanguardLTE\Repositories\Session\SessionRepository;
use VanguardLTE\User;

class InvalidateSessionsAndTokensTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('api_tokens');
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedInteger('user_id')->index();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('api_tokens');

        parent::tearDown();
    }

    public function testCredentialChangeInvalidatesSessionsAndApiTokens()
    {
        DB::table('api_tokens')->insert([
            ['id' => 'token-user-42', 'user_id' => 42],
            ['id' => 'token-other-user', 'user_id' => 7],
        ]);

        $sessions = $this->createMock(SessionRepository::class);
        $sessions->expects($this->once())
            ->method('invalidateAllSessionsForUser')
            ->with(42);

        $listener = new InvalidateSessionsAndTokens($sessions);
        $user = new User();
        $user->id = 42;

        $listener->handle(new UserCredentialsChanged($user, 'password_change'));

        $this->assertFalse(DB::table('api_tokens')->where('id', 'token-user-42')->exists());
        $this->assertTrue(DB::table('api_tokens')->where('id', 'token-other-user')->exists());
    }
}
