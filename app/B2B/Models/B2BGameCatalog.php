<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;
use VanguardLTE\B2B\Services\B2BGameCatalogCache;

class B2BGameCatalog extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DISABLED = 'disabled';
    const STATUS_MAINTENANCE = 'maintenance';

    protected $table = 'b2b_game_catalog';

    protected $fillable = [
        'game_uid',
        'provider_game_id',
        'canonical_game_id',
        'provider',
        'slug',
        'title',
        'category',
        'platform',
        'rtp',
        'volatility',
        'thumbnail_url',
        'launch_config',
        'demo_supported',
        'real_supported',
        'supported_currencies',
        'supported_countries',
        'status',
        'metadata',
    ];

    protected $casts = [
        'demo_supported' => 'boolean',
        'real_supported' => 'boolean',
        'launch_config' => 'array',
        'supported_currencies' => 'array',
        'supported_countries' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::saved(function () {
            app(B2BGameCatalogCache::class)->invalidate();
        });

        static::deleted(function () {
            app(B2BGameCatalogCache::class)->invalidate();
        });
    }

    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isMaintenance()
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }
}
