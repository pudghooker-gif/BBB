<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalHtmlBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);
        $this->app['session.store']->put('_token', 'csrf-test-token');

        Route::put('/local-form/{id}', function () {
            return 'ok';
        })->name('local.form.update');
        Route::getRoutes()->refreshNameLookups();
    }

    public function testFormOpenBuildsRouteActionMultipartAndSpoofedMethodFields()
    {
        $html = (string) app('form')->open([
            'route' => ['local.form.update', 7],
            'method' => 'PUT',
            'files' => true,
            'id' => 'demo-form',
        ]);

        $this->assertStringStartsWith('<form', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('action="' . route('local.form.update', 7) . '"', $html);
        $this->assertStringContainsString('accept-charset="UTF-8"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('id="demo-form"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertStringContainsString('name="_token" value="csrf-test-token"', $html);
        $this->assertSame('</form>', (string) app('form')->close());
    }

    public function testFormControlsRenderExpectedAttributesAndSelectionState()
    {
        $select = (string) app('form')->select(
            'status',
            ['0' => 'Disabled', '1' => 'Active'],
            '1',
            ['class' => 'form-control']
        );
        $multiSelect = (string) app('form')->select(
            'games[]',
            ['1' => 'Slots', '2' => 'Poker'],
            [1, '2'],
            ['multiple' => 'multiple']
        );
        $checkbox = (string) app('form')->checkbox('roles[1][]', 5, true, ['class' => 'permission-checkbox']);

        $this->assertStringContainsString('<select', $select);
        $this->assertStringContainsString('name="status"', $select);
        $this->assertStringContainsString('class="form-control"', $select);
        $this->assertStringContainsString('<option value="1" selected>Active</option>', $select);
        $this->assertStringContainsString('name="games[]"', $multiSelect);
        $this->assertStringContainsString('multiple="multiple"', $multiSelect);
        $this->assertSame(2, substr_count($multiSelect, ' selected'));
        $this->assertStringContainsString('type="checkbox"', $checkbox);
        $this->assertStringContainsString('name="roles[1][]"', $checkbox);
        $this->assertStringContainsString('value="5"', $checkbox);
        $this->assertStringContainsString('class="permission-checkbox"', $checkbox);
        $this->assertStringContainsString('checked', $checkbox);
    }

    public function testHtmlScriptUsesApplicationAssetUrl()
    {
        $script = (string) app('html')->script('/back/dist/js/pages/dashboard.js');

        $this->assertSame(
            '<script src="' . asset('/back/dist/js/pages/dashboard.js') . '"></script>',
            $script
        );
    }
}
