<?php

declare(strict_types=1);

namespace Tests;

use Anodyne\TablerIcons\BladeTablerIconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Orchestra\Testbench\TestCase;

class CompilesIconsTest extends TestCase
{
    /** @test */
    public function it_compiles_a_single_anonymous_component()
    {
        $result = svg('tabler-2fa')->toHtml();

        // Note: the empty class here seems to be a Blade components bug.
        $expected = <<<SVG
        <svg class="tabler-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <path d="M7 16h-4l3.47 -4.66a2 2 0 1 0 -3.47 -1.54" />
          <path d="M10 16v-8h4" />
          <path d="M10 12l3 0" />
          <path d="M17 16v-6a2 2 0 0 1 4 0v6" />
          <path d="M17 13l4 0" />
        </svg>
        SVG;

        $this->assertStringMatchesFormat($result, $expected);
    }

    /** @test */
    public function it_can_add_classes_to_icons()
    {
        $result = svg('tabler-2fa', 'w-6 h-6 text-gray-500')->toHtml();

        $expected = <<<SVG
        <svg class="tabler-icon w-6 h-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <path d="M7 16h-4l3.47 -4.66a2 2 0 1 0 -3.47 -1.54" />
          <path d="M10 16v-8h4" />
          <path d="M10 12l3 0" />
          <path d="M17 16v-6a2 2 0 0 1 4 0v6" />
          <path d="M17 13l4 0" />
        </svg>
        SVG;

        $this->assertStringMatchesFormat($expected, $result);
    }

    /** @test */
    public function it_can_add_styles_to_icons()
    {
        $result = svg('tabler-2fa', ['style' => 'color: #555'])->toHtml();

        $expected = <<<SVG
        <svg style="color: #555" class="tabler-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <path d="M7 16h-4l3.47 -4.66a2 2 0 1 0 -3.47 -1.54" />
          <path d="M10 16v-8h4" />
          <path d="M10 12l3 0" />
          <path d="M17 16v-6a2 2 0 0 1 4 0v6" />
          <path d="M17 13l4 0" />
        </svg>
        SVG;

        $this->assertStringMatchesFormat($expected, $result);
    }

    protected function getPackageProviders($app)
    {
        return [
            BladeIconsServiceProvider::class,
            BladeTablerIconsServiceProvider::class,
        ];
    }
}
