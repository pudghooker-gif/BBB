<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BGameCatalogSyncCommandTest extends TestCase
{
    use B2BApiTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'b2b.game_catalog_cache_enabled' => true,
            'b2b.game_catalog_cache_store' => 'array',
        ]);
        Cache::store('array')->flush();
        $this->resetB2BTables();
    }

    public function testSyncGamesCanSoftDisableMissingShopGames()
    {
        DB::table('games')->insert([
            [
                'name' => 'sync_keep_a',
                'title' => 'Sync Keep A',
                'shop_id' => 10,
                'view' => 1,
                'category' => 'slots',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'sync_other_shop',
                'title' => 'Sync Other Shop',
                'shop_id' => 20,
                'view' => 1,
                'category' => 'slots',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('b2b_game_catalog')->insert([
            [
                'game_uid' => 'sync_missing_a',
                'provider' => 'goldsvet_internal',
                'title' => 'Sync Missing A',
                'category' => 'slots',
                'demo_supported' => true,
                'real_supported' => true,
                'status' => 'active',
                'metadata' => json_encode(['synced_from' => 'games', 'shop_id' => 10]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'game_uid' => 'sync_missing_other_shop',
                'provider' => 'goldsvet_internal',
                'title' => 'Sync Missing Other Shop',
                'category' => 'slots',
                'demo_supported' => true,
                'real_supported' => true,
                'status' => 'active',
                'metadata' => json_encode(['synced_from' => 'games', 'shop_id' => 20]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'game_uid' => 'sync_external_provider',
                'provider' => 'external_provider',
                'title' => 'External Provider Game',
                'category' => 'slots',
                'demo_supported' => true,
                'real_supported' => true,
                'status' => 'active',
                'metadata' => json_encode(['synced_from' => 'external']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $exitCode = Artisan::call('b2b:sync-games', [
            '--shop_id' => 10,
            '--soft-disable-missing' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('soft-disabled: 1', $output);
        $this->assertStringContainsString('B2B game catalog cache invalidated.', $output);

        $this->assertSame('active', DB::table('b2b_game_catalog')->where('game_uid', 'sync_keep_a')->value('status'));
        $this->assertSame('sync_keep_a', DB::table('b2b_game_catalog')->where('game_uid', 'sync_keep_a')->value('provider_game_id'));
        $this->assertSame('sync_keep_a', DB::table('b2b_game_catalog')->where('game_uid', 'sync_keep_a')->value('canonical_game_id'));
        $this->assertSame('sync-keep-a', DB::table('b2b_game_catalog')->where('game_uid', 'sync_keep_a')->value('slug'));
        $this->assertSame('web', DB::table('b2b_game_catalog')->where('game_uid', 'sync_keep_a')->value('platform'));
        $this->assertSame(['launch_mode' => 'legacy_launcher'], json_decode(DB::table('b2b_game_catalog')->where('game_uid', 'sync_keep_a')->value('launch_config'), true));
        $this->assertSame('disabled', DB::table('b2b_game_catalog')->where('game_uid', 'sync_missing_a')->value('status'));
        $this->assertSame('active', DB::table('b2b_game_catalog')->where('game_uid', 'sync_missing_other_shop')->value('status'));
        $this->assertSame('active', DB::table('b2b_game_catalog')->where('game_uid', 'sync_external_provider')->value('status'));

        $metadata = json_decode(DB::table('b2b_game_catalog')->where('game_uid', 'sync_missing_a')->value('metadata'), true);
        $this->assertSame('missing_from_games_source', $metadata['disabled_by_sync_reason']);
        $this->assertArrayHasKey('disabled_by_sync_at', $metadata);
    }

    public function testSoftDisableMissingCannotRunWithPartialLimit()
    {
        DB::table('games')->insert([
            'name' => 'sync_limited_a',
            'title' => 'Sync Limited A',
            'shop_id' => 10,
            'view' => 1,
            'category' => 'slots',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('b2b:sync-games', [
            '--limit' => 1,
            '--soft-disable-missing' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('cannot be combined with --limit', Artisan::output());
    }
}
