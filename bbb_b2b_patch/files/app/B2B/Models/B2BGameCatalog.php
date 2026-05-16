<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BGameCatalog extends Model
{
    protected $table = 'b2b_game_catalog';

    protected $fillable = [
        'game_uid',
        'provider',
        'title',
        'category',
        'rtp',
        'volatility',
        'thumbnail_url',
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
        'supported_currencies' => 'array',
        'supported_countries' => 'array',
        'metadata' => 'array',
    ];
}
