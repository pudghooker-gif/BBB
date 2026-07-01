<?php

namespace Tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use VanguardLTE\Support\Validation\SecurityHardenedValidator;

class SecurityHardenedValidatorTest extends TestCase
{
    public function testEmailRuleRejectsCrlfInjectionPayloads()
    {
        $validator = Validator::make(
            ['email' => "player@example.test\r\nBcc: attacker@example.test"],
            ['email' => 'email']
        );

        $this->assertInstanceOf(SecurityHardenedValidator::class, $validator);
        $this->assertTrue($validator->fails());
    }

    public function testEmailRuleAcceptsNormalAddresses()
    {
        $validator = Validator::make(
            ['email' => 'player@example.test'],
            ['email' => 'email']
        );

        $this->assertInstanceOf(SecurityHardenedValidator::class, $validator);
        $this->assertFalse($validator->fails());
    }

    public function testMimeRuleRejectsPhp8OriginalUploadExtension()
    {
        $this->withUploadedPng('avatar.php8', function (UploadedFile $file) {
            $validator = Validator::make(['file' => $file], ['file' => 'file|mimes:png']);

            $this->assertInstanceOf(SecurityHardenedValidator::class, $validator);
            $this->assertTrue($validator->fails());
        });
    }

    public function testMimeRuleAcceptsPngOriginalUploadExtension()
    {
        $this->withUploadedPng('avatar.png', function (UploadedFile $file) {
            $validator = Validator::make(['file' => $file], ['file' => 'file|mimes:png']);

            $this->assertInstanceOf(SecurityHardenedValidator::class, $validator);
            $this->assertFalse($validator->fails());
        });
    }

    private function withUploadedPng($originalName, callable $callback)
    {
        $path = tempnam(sys_get_temp_dir(), 'bbb-upload-test-');
        $this->assertNotFalse($path);

        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lUcQGQAAAABJRU5ErkJggg=='));

        try {
            $callback(new UploadedFile($path, $originalName, 'image/png', null, true));
        } finally {
            @unlink($path);
        }
    }
}
