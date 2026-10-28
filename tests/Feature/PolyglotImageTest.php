<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Internal\Pasak\Models\BlockedIp;
use Internal\Pasak\Models\SecurityLog;
use Internal\Pasak\Rules\SafeImageFile;
use Internal\Pasak\Services\PasakServerSecurityService;

beforeEach(function () {
    config()->set('pasak.enabled', true);
    config()->set('pasak.block_enforcement', true);
    config()->set('pasak.instant_block.enabled', true);
    config()->set('pasak.instant_block.duration_hours', 720);
    config()->set('pasak.whitelist', []);
});

test('polyglot jpeg containing php code triggers an instant block', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.90']);

    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $jpegData = ob_get_clean();
    imagedestroy($img);

    $file = UploadedFile::fake()->createWithContent('innocent_photo.jpg', $jpegData.'<?php

use Internal\Pasak\Models\SecurityLog; phpinfo(); ?>');

    $response = $this->post('/login', ['avatar' => $file]);

    $response->assertForbidden();

    $block = BlockedIp::query()->where('ip_address', '198.51.100.90')->first();

    expect($block)->not->toBeNull()
        ->and($block->source)->toBe('automatic')
        ->and($block->is_active)->toBeTrue();

    expect(SecurityLog::query()->where('ip_address', '198.51.100.90')->whereIn('event_type', ['php_injection', 'webshell_upload'])->exists())->toBeTrue();
});

test('clean jpeg does not trigger a block', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.91']);

    $file = UploadedFile::fake()->image('clean_photo.jpg', 20, 20);

    $this->post('/login', ['avatar' => $file]);

    expect(BlockedIp::query()->where('ip_address', '198.51.100.91')->exists())->toBeFalse();
});

test('safe image file rule rejects polyglot jpeg with embedded php', function () {
    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $jpegData = ob_get_clean();
    imagedestroy($img);

    $file = UploadedFile::fake()->createWithContent('test.jpg', $jpegData."<?php

use Internal\Pasak\Models\SecurityLog; eval(\$_POST['x']); ?>");

    $validator = Validator::make(['icon' => $file], [
        'icon' => [new SafeImageFile],
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('icon'))->toContain('tidak diizinkan');
});

test('safe image file rule rejects svg with script or event handlers', function () {
    $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
    $file = UploadedFile::fake()->createWithContent('malicious.svg', $svgContent);

    $validator = Validator::make(['icon' => $file], [
        'icon' => [new SafeImageFile],
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('icon'))->toContain('tidak diizinkan');

    $svgEvent = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>';
    $fileEvent = UploadedFile::fake()->createWithContent('event.svg', $svgEvent);

    $validatorEvent = Validator::make(['icon' => $fileEvent], [
        'icon' => [new SafeImageFile],
    ]);

    expect($validatorEvent->fails())->toBeTrue()
        ->and($validatorEvent->errors()->first('icon'))->toContain('script atau event handler berbahaya');
});

test('safe image file rule accepts clean png and svg', function () {
    $pngFile = UploadedFile::fake()->image('clean.png', 30, 30);

    $validator = Validator::make(['icon' => $pngFile], [
        'icon' => [new SafeImageFile],
    ]);

    expect($validator->passes())->toBeTrue();

    $cleanSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="red"/></svg>';
    $svgFile = UploadedFile::fake()->createWithContent('clean.svg', $cleanSvg);

    $validatorSvg = Validator::make(['icon' => $svgFile], [
        'icon' => [new SafeImageFile],
    ]);

    expect($validatorSvg->passes())->toBeTrue();
});

test('safe image file rule and server scanner accept legitimate jpeg with accidental byte collision', function () {
    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $jpegData = ob_get_clean();
    imagedestroy($img);

    // Accidental byte sequence "<?=k" followed by non-PHP binary bytes (common DCT/Huffman collision).
    $binaryCollision = $jpegData."\x6c\x0f\x5a\x3c\x3f\x3d\x6b\xa3\xe8\x0f\xd9";
    $file = UploadedFile::fake()->createWithContent('innocent_photo.jpg', $binaryCollision);

    $validator = Validator::make(['icon' => $file], [
        'icon' => [new SafeImageFile],
    ]);

    expect($validator->passes())->toBeTrue();

    // Verify ServerSecurityService inspectFileContent directly on collision payload
    $reflection = new ReflectionClass(PasakServerSecurityService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('inspectFileContent');

    $tempPath = tempnam(sys_get_temp_dir(), 'pasak_test_');
    file_put_contents($tempPath, $binaryCollision);

    try {
        $result = $method->invoke($service, $tempPath, 'innocent_photo.jpg', 'jpg', strlen($binaryCollision));
        expect($result['is_suspicious'])->toBeFalse();
    } finally {
        @unlink($tempPath);
    }
});

test('safe image file rule rejects short tag webshells with variables or functions', function () {
    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $jpegData = ob_get_clean();
    imagedestroy($img);

    $webshellWithFunc = UploadedFile::fake()->createWithContent('shell_func.jpg', $jpegData.'<?=system($_GET["c"])?>');
    $validator1 = Validator::make(['icon' => $webshellWithFunc], [
        'icon' => [new SafeImageFile],
    ]);
    expect($validator1->fails())->toBeTrue();

    $webshellWithVar = UploadedFile::fake()->createWithContent('shell_var.jpg', $jpegData.'<?=$_GET["c"]?>');
    $validator2 = Validator::make(['icon' => $webshellWithVar], [
        'icon' => [new SafeImageFile],
    ]);
    expect($validator2->fails())->toBeTrue();
});
