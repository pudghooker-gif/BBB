<?php

namespace VanguardLTE\B2B\Services;

use VanguardLTE\B2B\Models\B2BGameCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BGameAvailabilityService
{
    public function availableForLaunch($operator, $gameUid, $currency = null, $country = null, $mode = 'real')
    {
        $gameUid = (string) $gameUid;
        if (!$this->operatorAllowsGame($operator, $gameUid)) {
            return $this->unavailable('GAME_NOT_AVAILABLE', 'Game is not enabled for this operator.');
        }

        $catalogGame = $this->catalogGame($gameUid);
        if ($catalogGame) {
            if (!$this->catalogSupports($catalogGame, $currency, $country, $mode)) {
                return $this->unavailable('GAME_NOT_AVAILABLE', 'Game is not available for the requested currency, country, or mode.');
            }

            return ['ok' => true, 'provider' => $catalogGame->provider, 'source' => 'b2b_game_catalog'];
        }

        if ($this->legacyGameExists($gameUid)) {
            if ($this->legacyGameAvailableForOperator($operator, $gameUid)) {
                return ['ok' => true, 'provider' => 'goldsvet_internal', 'source' => 'games'];
            }

            return $this->unavailable('GAME_NOT_AVAILABLE', 'Game is not enabled for this operator.');
        }

        return $this->unavailable('GAME_NOT_AVAILABLE', 'Game was not found in the B2B catalog.');
    }

    public function operatorAllowsGame($operator, $gameUid)
    {
        $settings = $operator && is_array($operator->settings) ? $operator->settings : [];

        if (isset($settings['disabled_games']) && is_array($settings['disabled_games'])) {
            if (in_array($gameUid, array_map('strval', $settings['disabled_games']), true)) {
                return false;
            }
        }

        if (isset($settings['enabled_games']) && is_array($settings['enabled_games']) && count($settings['enabled_games']) > 0) {
            return in_array($gameUid, array_map('strval', $settings['enabled_games']), true);
        }

        if (!$this->legacyGameExists($gameUid)) {
            return true;
        }

        return $this->legacyGameAvailableForOperator($operator, $gameUid);
    }

    public function catalogSupports($catalogGame, $currency = null, $country = null, $mode = 'real')
    {
        if (!$catalogGame) {
            return false;
        }

        if ($mode === 'demo' && !$catalogGame->demo_supported) {
            return false;
        }

        if ($mode !== 'demo' && !$catalogGame->real_supported) {
            return false;
        }

        if ($currency && is_array($catalogGame->supported_currencies) && count($catalogGame->supported_currencies) > 0) {
            if (!in_array(strtoupper($currency), array_map('strtoupper', $catalogGame->supported_currencies), true)) {
                return false;
            }
        }

        if ($country && is_array($catalogGame->supported_countries) && count($catalogGame->supported_countries) > 0) {
            if (!in_array(strtoupper($country), array_map('strtoupper', $catalogGame->supported_countries), true)) {
                return false;
            }
        }

        return true;
    }

    private function catalogGame($gameUid)
    {
        if (!Schema::hasTable('b2b_game_catalog')) {
            return null;
        }

        return B2BGameCatalog::query()
            ->where('game_uid', $gameUid)
            ->where('status', 'active')
            ->first();
    }

    private function legacyGameExists($gameUid)
    {
        if (!Schema::hasTable('games') || !Schema::hasColumn('games', 'name')) {
            return false;
        }

        return DB::table('games')->where('name', $gameUid)->exists();
    }

    private function legacyGameAvailableForOperator($operator, $gameUid)
    {
        if (!$operator || !isset($operator->shop_id) || !$operator->shop_id) {
            return true;
        }

        if (!Schema::hasTable('games') || !Schema::hasColumn('games', 'name')) {
            return false;
        }

        $query = DB::table('games')
            ->where('name', $gameUid)
            ->where('shop_id', $operator->shop_id);

        if (Schema::hasColumn('games', 'view')) {
            $query->where('view', 1);
        }

        return $query->exists();
    }

    private function unavailable($code, $message)
    {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}
