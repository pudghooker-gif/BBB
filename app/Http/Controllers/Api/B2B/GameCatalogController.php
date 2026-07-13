<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BGameCatalog;
use VanguardLTE\B2B\Services\B2BGameAvailabilityService;
use VanguardLTE\B2B\Services\B2BGameCatalogCache;
use VanguardLTE\B2B\Support\B2BApiResponse;
use VanguardLTE\Game;

class GameCatalogController extends Controller
{
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 500;
    private const SORT_COLUMNS = [
        'title' => 'title',
        'provider' => 'provider',
        'category' => 'category',
        'game_uid' => 'game_uid',
    ];

    public function index(Request $request, B2BGameAvailabilityService $availability, B2BGameCatalogCache $cache)
    {
        $operator = $request->attributes->get('b2b_operator');
        $filters = $this->validatedIndexFilters($request);

        if (isset($filters['response'])) {
            return $filters['response'];
        }

        $payload = $cache->rememberIndex($operator, $filters, function () use ($operator, $availability, $filters) {
            return $this->indexPayload($operator, $availability, $filters);
        });

        return B2BApiResponse::success($request, $payload['data'], 200, $payload['meta']);
    }

    private function indexPayload($operator, B2BGameAvailabilityService $availability, array $filters)
    {
        $query = B2BGameCatalog::query()->where('status', B2BGameCatalog::STATUS_ACTIVE);

        $this->applyCatalogFilters($query, $filters);
        $this->applyCatalogSort($query, $filters['sort']);

        $games = $query->get()
            ->filter(function ($game) use ($operator, $availability, $filters) {
                $result = $availability->availableForLaunch(
                    $operator,
                    $game->game_uid,
                    $filters['currency'],
                    $filters['country'],
                    $filters['mode']
                );

                return $result['ok'];
            })
            ->map(function ($game) {
                return $this->catalogPayload($game);
            })
            ->values();
        $source = 'b2b_game_catalog';

        if ($games->count() === 0 && $operator && $operator->shop_id) {
            $games = $this->fallbackFromGoldsvetGames($operator->shop_id, $operator, $availability, $filters);
            $source = 'goldsvet_internal';
        }

        $availableCount = $games->count();
        $limited = $games->take($filters['limit'])->values();

        return [
            'data' => $limited->all(),
            'meta' => [
                'limit' => $filters['limit'],
                'count' => $limited->count(),
                'available_count' => $availableCount,
                'sort' => $filters['sort'] ?: 'provider,title',
                'filters' => $this->responseFilters($filters),
                'source' => $source,
            ],
        ];
    }

    public function show(Request $request, B2BGameAvailabilityService $availability, $gameUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        $validator = Validator::make($request->query(), [
            'currency' => 'nullable|string|size:3',
            'country' => 'nullable|string|size:2',
            'mode' => 'nullable|in:real,demo',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $currency = $request->query('currency');
        $country = $request->query('country');
        $mode = $request->query('mode', 'real');

        $result = $availability->availableForLaunch($operator, $gameUid, $currency, $country, $mode);
        if (!$result['ok']) {
            return B2BApiResponse::error(
                $request,
                $result['code'],
                isset($result['message']) ? $result['message'] : null
            );
        }

        $game = B2BGameCatalog::query()
            ->where('game_uid', (string) $gameUid)
            ->where('status', B2BGameCatalog::STATUS_ACTIVE)
            ->first();

        if ($game) {
            return B2BApiResponse::success($request, $this->catalogPayload($game));
        }

        $legacy = Game::where('name', (string) $gameUid)
            ->when($operator && $operator->shop_id, function ($query) use ($operator) {
                $query->where('shop_id', $operator->shop_id);
            })
            ->where('view', 1)
            ->first();

        if (!$legacy) {
            return B2BApiResponse::error($request, 'GAME_NOT_AVAILABLE');
        }

        return B2BApiResponse::success($request, $this->legacyPayload($legacy));
    }

    private function fallbackFromGoldsvetGames($shopId, $operator, B2BGameAvailabilityService $availability, array $filters)
    {
        if ($filters['provider'] && strtolower($filters['provider']) !== 'goldsvet_internal') {
            return collect();
        }

        $query = Game::where('shop_id', $shopId)
            ->where('view', 1)
            ->when($filters['category'] && Schema::hasColumn('games', 'category'), function ($q) use ($filters) {
                $q->where('category', $filters['category']);
            })
            ->when($filters['search'], function ($q) use ($filters) {
                $needle = '%' . $filters['search'] . '%';
                $q->where(function ($inner) use ($needle) {
                    $inner->where('name', 'like', $needle);

                    if (Schema::hasColumn('games', 'title')) {
                        $inner->orWhere('title', 'like', $needle);
                    }
                });
            });

        $this->applyLegacySort($query, $filters['sort']);

        return $query
            ->get()
            ->filter(function ($game) use ($operator, $availability, $filters) {
                $result = $availability->availableForLaunch(
                    $operator,
                    $game->name,
                    $filters['currency'],
                    $filters['country'],
                    $filters['mode']
                );

                return $result['ok'];
            })
            ->map(function ($game) {
                return $this->legacyPayload($game);
            })
            ->values();
    }

    private function validatedIndexFilters(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'provider' => 'nullable|string|max:80',
            'category' => 'nullable|string|max:80',
            'platform' => 'nullable|string|max:30',
            'search' => 'nullable|string|max:120',
            'currency' => 'nullable|string|size:3',
            'country' => 'nullable|string|size:2',
            'mode' => 'nullable|in:real,demo',
            'sort' => 'nullable|in:title,-title,provider,-provider,category,-category,game_uid,-game_uid',
        ]);

        if ($validator->fails()) {
            return [
                'response' => B2BApiResponse::error(
                    $request,
                    'VALIDATION_FAILED',
                    null,
                    422,
                    $validator->errors()
                ),
            ];
        }

        $limit = $request->query('limit');

        return [
            'limit' => $limit === null || $limit === '' ? self::DEFAULT_LIMIT : (int) $limit,
            'provider' => $this->normalizedTextFilter($request, 'provider'),
            'category' => $this->normalizedTextFilter($request, 'category'),
            'platform' => $this->normalizedTextFilter($request, 'platform'),
            'search' => $this->normalizedTextFilter($request, 'search'),
            'currency' => $this->normalizedUpperFilter($request, 'currency'),
            'country' => $this->normalizedUpperFilter($request, 'country'),
            'mode' => $this->normalizedTextFilter($request, 'mode') ?: 'real',
            'sort' => $this->normalizedTextFilter($request, 'sort'),
        ];
    }

    private function applyCatalogFilters($query, array $filters)
    {
        if ($filters['provider']) {
            $query->where('provider', $filters['provider']);
        }

        if ($filters['category']) {
            $query->where('category', $filters['category']);
        }

        if ($filters['platform']) {
            $query->where('platform', $filters['platform']);
        }

        if ($filters['search']) {
            $needle = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('game_uid', 'like', $needle)
                    ->orWhere('provider_game_id', 'like', $needle)
                    ->orWhere('canonical_game_id', 'like', $needle)
                    ->orWhere('slug', 'like', $needle)
                    ->orWhere('title', 'like', $needle);
            });
        }

        if ($filters['currency']) {
            $currency = $filters['currency'];
            $query->where(function ($q) use ($currency) {
                $q->whereNull('supported_currencies')
                    ->orWhere('supported_currencies', 'like', '%"' . $currency . '"%');
            });
        }

        if ($filters['country']) {
            $country = $filters['country'];
            $query->where(function ($q) use ($country) {
                $q->whereNull('supported_countries')
                    ->orWhere('supported_countries', 'like', '%"' . $country . '"%');
            });
        }

        if ($filters['mode'] === 'demo') {
            $query->where('demo_supported', true);
        } else {
            $query->where('real_supported', true);
        }
    }

    private function applyCatalogSort($query, $sort)
    {
        if (!$sort) {
            $query->orderBy('provider')->orderBy('title')->orderBy('game_uid');
            return;
        }

        list($column, $direction) = $this->sortParts($sort);
        $query->orderBy(self::SORT_COLUMNS[$column], $direction)->orderBy('game_uid');
    }

    private function applyLegacySort($query, $sort)
    {
        if (!$sort) {
            $query->orderBy('name');
            return;
        }

        list($column, $direction) = $this->sortParts($sort);

        if ($column === 'title' && Schema::hasColumn('games', 'title')) {
            $query->orderBy('title', $direction)->orderBy('name');
            return;
        }

        if ($column === 'category' && Schema::hasColumn('games', 'category')) {
            $query->orderBy('category', $direction)->orderBy('name');
            return;
        }

        if ($column === 'game_uid') {
            $query->orderBy('name', $direction);
            return;
        }

        $query->orderBy('name');
    }

    private function sortParts($sort)
    {
        $direction = strpos($sort, '-') === 0 ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return [$column, $direction];
    }

    private function normalizedTextFilter(Request $request, $key)
    {
        $value = $request->query($key);
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizedUpperFilter(Request $request, $key)
    {
        $value = $this->normalizedTextFilter($request, $key);

        return $value === null ? null : strtoupper($value);
    }

    private function responseFilters(array $filters)
    {
        return array_filter([
            'provider' => $filters['provider'],
            'category' => $filters['category'],
            'platform' => $filters['platform'],
            'search' => $filters['search'],
            'currency' => $filters['currency'],
            'country' => $filters['country'],
            'mode' => $filters['mode'],
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function catalogPayload(B2BGameCatalog $game)
    {
        return [
            'game_uid' => $game->game_uid,
            'provider_game_id' => $game->provider_game_id,
            'canonical_game_id' => $game->canonical_game_id,
            'provider' => $game->provider,
            'slug' => $game->slug,
            'title' => $game->title,
            'category' => $game->category,
            'platform' => $game->platform,
            'rtp' => $game->rtp,
            'volatility' => $game->volatility,
            'thumbnail_url' => $game->thumbnail_url,
            'launch_config' => $game->launch_config ?: [],
            'demo_supported' => (bool) $game->demo_supported,
            'real_supported' => (bool) $game->real_supported,
            'supported_currencies' => $game->supported_currencies ?: [],
            'supported_countries' => $game->supported_countries ?: [],
            'status' => $game->status,
            'metadata' => $game->metadata ?: [],
        ];
    }

    private function legacyPayload($game)
    {
        return [
            'game_uid' => $game->name,
            'provider_game_id' => $game->name,
            'canonical_game_id' => $game->name,
            'provider' => 'goldsvet_internal',
            'slug' => Str::slug($game->title ?: $game->name) ?: $game->name,
            'title' => $game->title ?: $game->name,
            'category' => isset($game->category) ? $game->category : 'slots',
            'platform' => 'web',
            'launch_config' => [
                'launch_mode' => 'legacy_launcher',
            ],
            'demo_supported' => true,
            'real_supported' => true,
            'supported_currencies' => [],
            'supported_countries' => [],
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
