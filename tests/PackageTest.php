<?php

declare(strict_types=1);

use Anodyne\TablerIcons\BladeTablerIconsServiceProvider;
use Anodyne\TablerIcons\Tabler;
use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

test('every svg has exactly one enum case and valid drawing', function () {
    $files = glob(dirname(__DIR__).'/resources/svg/*.svg');
    $expected = array_map(static fn ($file) => 'tabler-'.basename($file, '.svg'), $files);
    $actual = array_map(static fn (Tabler $icon) => $icon->value, Tabler::cases());
    sort($expected);
    sort($actual);
    $this->assertNotEmpty($expected);
    $this->assertSame($expected, $actual);
    $this->assertCount(count($actual), array_unique($actual));

    foreach ($files as $file) {
        $document = new DOMDocument;
        $this->assertTrue($document->load($file, LIBXML_NONET), $file);
        $this->assertNull($document->doctype, $file);
        $root = $document->documentElement;
        $this->assertSame('svg', $root->localName, $file);
        $this->assertSame('http://www.w3.org/2000/svg', $root->namespaceURI, $file);
        $this->assertSame('0 0 24 24', $root->getAttribute('viewBox'), $file);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
        $this->assertGreaterThan(0, $xpath->query('//svg:path[string-length(@d) > 0 and not(@stroke="none") and not(@fill="none")] | //svg:circle[@r > 0] | //svg:ellipse[@rx > 0] | //svg:rect[@width > 0] | //svg:line[@x1] | //svg:polyline[@points] | //svg:polygon[@points]')->length, $file);
    }
});

test('blade component and directive render classes and styles', function () {
    $expected = svg('tabler-abc', 'w-6', ['style' => 'color: #555'])->toHtml();
    $this->assertSame($expected, trim(Blade::render('<x-tabler-abc class="w-6" style="color: #555" />')));
    $this->assertSame($expected, trim(Blade::render("@svg('tabler-abc', 'w-6', ['style' => 'color: #555'])")));
    $this->assertSame(svg('tabler-abc')->toHtml(), svg(Tabler::Abc->value)->toHtml());
});

test('filled and outline styles are preserved', function () {
    $this->assertStringContainsString('fill="currentColor"', svg(Tabler::HeartFilled->value)->toHtml());
    $outline = svg(Tabler::Heart->value)->toHtml();
    $this->assertStringContainsString('fill="none"', $outline);
    $this->assertStringContainsString('stroke="currentColor"', $outline);
    $this->assertStringContainsString('stroke-width="2"', $outline);
});

test('unknown icon throws missing icon exception', function () {
    $this->expectException(SvgNotFound::class);
    svg('tabler-this-icon-does-not-exist');
});

test('publishing copies every bundled svg', function () {
    $temporary = sys_get_temp_dir().'/tabler-publish-'.bin2hex(random_bytes(8));
    $this->app->usePublicPath($temporary);
    // Register publishing paths again after assigning this test's isolated public path.
    (new BladeTablerIconsServiceProvider($this->app))->boot();
    $destination = $temporary.'/vendor/blade-tabler-icons';
    $paths = ServiceProvider::pathsToPublish(BladeTablerIconsServiceProvider::class, 'blade-tabler-icons');
    $this->assertSame([dirname(__DIR__).'/src/../resources/svg' => $destination], $paths);
    try {
        mkdir($destination, 0777, true);
        file_put_contents($destination.'/abc.svg', 'customized published icon');
        $this->artisan('vendor:publish', ['--tag' => 'blade-tabler-icons'])->assertExitCode(0);
        expect(file_get_contents($destination.'/abc.svg'))->toBe('customized published icon');
        $this->artisan('vendor:publish', ['--tag' => 'blade-tabler-icons', '--force' => true])->assertExitCode(0);
        $sourceFiles = glob(dirname(__DIR__).'/resources/svg/*.svg');
        $this->assertCount(count($sourceFiles), glob($destination.'/*.svg'));
        foreach ($sourceFiles as $source) {
            $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $destination.'/'.basename($source)));
        }
    } finally {
        $this->app['files']->deleteDirectory($temporary);
    }
    $this->assertDirectoryDoesNotExist($temporary);
});

test('renders both variants through every Blade entry point with accessible attributes', function (string $name) {
    $attributes = ['role' => 'img', 'aria-label' => e('Heart & "care"'), 'style' => 'color: #555'];
    $outputs = [
        svg('tabler-'.$name, 'w-6', $attributes)->toHtml(),
        Blade::render('<x-tabler-'.$name.' class="w-6" role="img" aria-label="Heart &amp; &quot;care&quot;" style="color: #555" />'),
        Blade::render("@svg('tabler-{$name}', 'w-6', \$attributes)", ['attributes' => $attributes]),
    ];
    foreach ($outputs as $output) {
        $document = new DOMDocument;
        expect($document->loadXML(trim($output), LIBXML_NONET))->toBeTrue();
        $root = $document->documentElement;
        expect($root->getAttribute('class'))->toBe('tabler-icon w-6')
            ->and($root->getAttribute('role'))->toBe('img')
            ->and($root->getAttribute('aria-label'))->toBe('Heart & "care"')
            ->and($root->getAttribute('style'))->toBe('color: #555')
            ->and($root->getAttribute('fill'))->toBe($name === 'heart-filled' ? 'currentColor' : 'none');
    }
})->with(['outline' => ['heart'], 'filled' => ['heart-filled']]);

test('registers icons whether the factory is resolved before or after the provider', function (bool $resolveFirst) {
    $container = new Container;
    $container->singleton(Factory::class, function () {
        $files = new Filesystem;

        return new Factory($files, new IconsManifest($files, '/unused-tabler-manifest.php'));
    });
    if ($resolveFirst) {
        $container->make(Factory::class);
    }
    (new BladeTablerIconsServiceProvider($container))->register();
    $icon = $container->make(Factory::class)->svg('tabler-abc')->toHtml();
    expect($icon)->toBe(svg(Tabler::Abc->value)->toHtml());
})->with(['already resolved' => [true], 'lazy resolution' => [false]]);

test('does not register publishing paths outside the console', function () {
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('runningInConsole')->once()->andReturn(false);
    $before = ServiceProvider::pathsToPublish(BladeTablerIconsServiceProvider::class);
    (new BladeTablerIconsServiceProvider($application))->boot();
    expect(ServiceProvider::pathsToPublish(BladeTablerIconsServiceProvider::class))->toBe($before);
});
