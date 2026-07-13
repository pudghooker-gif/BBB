<?php

namespace VanguardLTE\B2B\Services;

use VanguardLTE\B2B\Models\B2BGameCatalog;
use VanguardLTE\B2B\Models\B2BOperatorGameAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BGameAvailabilityService
{
    public function availableForLaunch($operator, $gameUid, $currency = null, $country = null, $mode = 'real')
    {
        $gameUid = (string) $gameUid;

        $catalogGame = $this->catalogGame($gameUid);
        $provider = $catalogGame
            ? $catalogGame->provider
            : ($this->legacyGameExists($gameUid) ? 'goldsvet_internal' : null);

        $assignment = $this->operatorAssignmentDecision($operator, $gameUid, $currency, $country, $mode, $provider);
        if ($assignment && !$assignment['ok']) {
            return $this->unavailable('GAME_NOT_AVAILABLE', $assignment['message']);
        }

        if (!$assignment && !$this->operatorAllowsGame($operator, $gameUid, $provider)) {
            return $this->unavailable('GAME_NOT_AVAILABLE', 'Game is not enabled for this operator.');
        }

        if ($catalogGame) {
            $status = $this->catalogStatusDecision($catalogGame);
            if (!$status['ok']) {
                return $status;
            }

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

    public function catalogStatusDecision($catalogGame)
    {
        if (!$catalogGame) {
            return $this->unavailable('GAME_NOT_AVAILABLE', 'Game was not found in the B2B catalog.');
        }

        $status = isset($catalogGame->status) ? (string) $catalogGame->status : B2BGameCatalog::STATUS_ACTIVE;
        if ($status === B2BGameCatalog::STATUS_MAINTENANCE) {
            return $this->unavailable('GAME_UNDER_MAINTENANCE', 'Game is temporarily under maintenance.');
        }

        if ($status !== B2BGameCatalog::STATUS_ACTIVE) {
            return $this->unavailable('GAME_NOT_AVAILABLE', 'Game is disabled in the B2B catalog.');
        }

        return ['ok' => true];
    }

    public function operatorAllowsGame($operator, $gameUid, $provider = null)
    {
        $assignment = $this->operatorAssignmentDecision($operator, $gameUid, null, null, null, $provider);
        if ($assignment) {
            return $assignment['ok'];
        }

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

    public function operatorAssignmentDecision($operator, $gameUid, $currency = null, $country = null, $mode = null, $provider = null)
    {
        if (!$operator || !isset($operator->id) || !$this->hasAssignmentTable()) {
            return null;
        }

        $query = B2BOperatorGameAssignment::query()
            ->where('operator_id', $operator->id);

        $hasAllowedAssignments = (clone $query)
            ->where('status', B2BOperatorGameAssignment::STATUS_ALLOWED)
            ->exists();

        $assignment = (clone $query)
            ->where('game_uid', (string) $gameUid)
            ->when($provider, function ($q) use ($provider) {
                $q->where('provider', $provider);
            })
            ->whereIn('status', [
                B2BOperatorGameAssignment::STATUS_ALLOWED,
                B2BOperatorGameAssignment::STATUS_BLOCKED,
            ])
            ->first();

        if (!$assignment) {
            if (!$hasAllowedAssignments) {
                return null;
            }

            return [
                'ok' => false,
                'message' => 'Game is not assigned to this operator.',
            ];
        }

        if ($assignment->isBlocked()) {
            return [
                'ok' => false,
                'message' => 'Game is blocked for this operator.',
            ];
        }

        if ($mode === 'demo' && !$assignment->demo_enabled) {
            return [
                'ok' => false,
                'message' => 'Demo mode is not enabled for this operator game assignment.',
            ];
        }

        if ($mode !== null && $mode !== 'demo' && !$assignment->real_enabled) {
            return [
                'ok' => false,
                'message' => 'Real mode is not enabled for this operator game assignment.',
            ];
        }

        if ($currency && is_array($assignment->allowed_currencies) && count($assignment->allowed_currencies) > 0) {
            if (!in_array(strtoupper($currency), array_map('strtoupper', $assignment->allowed_currencies), true)) {
                return [
                    'ok' => false,
                    'message' => 'Currency is not enabled for this operator game assignment.',
                ];
            }
        }

        if ($country && is_array($assignment->allowed_countries) && count($assignment->allowed_countries) > 0) {
            if (!in_array(strtoupper($country), array_map('strtoupper', $assignment->allowed_countries), true)) {
                return [
                    'ok' => false,
                    'message' => 'Country is not enabled for this operator game assignment.',
                ];
            }
        }

        return [
            'ok' => true,
            'assignment' => $assignment,
        ];
    }

    private function catalogGame($gameUid)
    {
        if (!Schema::hasTable('b2b_game_catalog')) {
            return null;
        }

        return B2BGameCatalog::query()
            ->where('game_uid', $gameUid)
            ->first();
    }

    private function hasAssignmentTable()
    {
        return Schema::hasTable('b2b_operator_game_assignments');
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
