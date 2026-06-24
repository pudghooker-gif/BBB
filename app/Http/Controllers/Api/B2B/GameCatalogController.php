<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VanguardLTE\B2B\Models\B2BGameCatalog;
use VanguardLTE\B2B\Services\B2BGameAvailabilityService;
use VanguardLTE\B2B\Support\B2BApiResponse;
use VanguardLTE\Game;

class GameCatalogController extends Controller
{
    public function index(Request $request, B2BGameAvailabilityService $availability)
    {
        $operator = $request->attributes->get('b2b_operator');
        $currency = $request->query('currency');
        $country = $request->query('country');

        $query = B2BGameCatalog::query()->where('status', 'active');

        if ($currency) {
            $query->where(function ($q) use ($currency) {
                $q->whereNull('supported_currencies')
                    ->orWhere('supported_currencies', 'like', '%"' . $currency . '"%');
            });
        }

        if ($country) {
            $query->where(function ($q) use ($country) {
                $q->whereNull('supported_countries')
                    ->orWhere('supported_countries', 'like', '%"' . $country . '"%');
            });
        }

        $games = $query->orderBy('provider')->orderBy('title')->get()
            ->filter(function ($game) use ($operator, $availability) {
                return $availability->operatorAllowsGame($operator, $game->game_uid, $game->provider);
            })
            ->values();

        if ($games->count() === 0 && $operator && $operator->shop_id) {
            $games = $this->fallbackFromGoldsvetGames($operator->shop_id, $operator, $availability);
        }

        return B2BApiResponse::success($request, $games);
    }

    private function fallbackFromGoldsvetGames($shopId, $operator, B2BGameAvailabilityService $availability)
    {
        return Game::where('shop_id', $shopId)
            ->where('view', 1)
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->filter(function ($game) use ($operator, $availability) {
                return $availability->operatorAllowsGame($operator, $game->name, 'goldsvet_internal');
            })
            ->map(function ($game) {
                return [
                    'game_uid' => $game->name,
                    'provider' => 'goldsvet_internal',
                    'title' => $game->title ?: $game->name,
                    'category' => isset($game->category) ? $game->category : 'slots',
                    'demo_supported' => true,
                    'real_supported' => true,
                    'status' => 'active',
                ];
            })
            ->values();
    }
}
