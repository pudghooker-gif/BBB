<?php

namespace VanguardLTE\Listeners\Users;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\Events\User\Banned;
use VanguardLTE\Events\User\LoggedIn;
use VanguardLTE\Events\User\UserCredentialsChanged;
use VanguardLTE\Repositories\Session\SessionRepository;
use VanguardLTE\Repositories\User\UserRepository;
use VanguardLTE\Services\Auth\Api\Token;

class InvalidateSessionsAndTokens
{
    /**
     * @var SessionRepository
     */
    private $sessions;

    public function __construct(SessionRepository $sessions)
    {
        $this->sessions = $sessions;
    }

    /**
     * Handle the event.
     *
     * @param Banned|UserCredentialsChanged $event
     * @return void
     */
    public function handle($event)
    {
        $user = $this->userFromEvent($event);

        if (!$user) {
            return;
        }

        $this->sessions->invalidateAllSessionsForUser($user->id);

        if (Schema::hasTable('api_tokens')) {
            Token::where('user_id', $user->id)->delete();
        }
    }

    private function userFromEvent($event)
    {
        if ($event instanceof Banned) {
            return $event->getBannedUser();
        }

        if ($event instanceof UserCredentialsChanged) {
            return $event->getUser();
        }

        return null;
    }
}
