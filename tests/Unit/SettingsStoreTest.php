<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;
use VanguardLTE\Support\Settings\JsonSettingStore;

class SettingsStoreTest extends TestCase
{
    public function testJsonSettingsStorePersistsDotNotationValues()
    {
        $path = storage_path('framework/testing/settings-store.json');
        $files = new Filesystem();
        $files->delete($path);

        $store = new JsonSettingStore($files, $path);
        $store->set('app.name', 'BBB');
        $store->set('limits.max', 100);
        $store->save();

        $freshStore = new JsonSettingStore($files, $path);

        $this->assertSame('BBB', $freshStore->get('app.name'));
        $this->assertSame(100, $freshStore->get('limits.max'));
        $this->assertSame('fallback', $freshStore->get('missing', 'fallback'));

        $freshStore->forget('limits.max');
        $freshStore->save();

        $this->assertFalse((new JsonSettingStore($files, $path))->has('limits.max'));
    }

    public function testSettingsHelperUsesLocalStoreBinding()
    {
        $this->assertInstanceOf(JsonSettingStore::class, settings());
        $this->assertSame(settings(), app('anlutro\LaravelSettings\SettingStore'));
        $this->assertSame(settings(), app('setting'));
    }
}
