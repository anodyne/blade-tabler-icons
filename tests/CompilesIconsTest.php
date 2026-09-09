<?php

declare(strict_types=1);

test('it compiles a single anonymous component', function () {
    $result = svg('tabler-abc')->toHtml();

    $expected = <<<'SVG'
    <svg class="tabler-icon" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path stroke="none" d="M0 0h24v24H0z"/><path d="M3 16v-6a2 2 0 1 1 4 0v6M3 13h4M10 8v6a2 2 0 1 0 4 0v-1a2 2 0 1 0-4 0v1M20.732 12A2 2 0 0 0 17 13v1a2 2 0 0 0 3.726 1.01"/></svg>
    SVG;

    $this->assertSame($expected, $result);
});

test('it can add classes to icons', function () {
    $result = svg('tabler-abc', 'w-6 h-6 text-gray-500')->toHtml();

    $expected = <<<'SVG'
    <svg class="tabler-icon w-6 h-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path stroke="none" d="M0 0h24v24H0z"/><path d="M3 16v-6a2 2 0 1 1 4 0v6M3 13h4M10 8v6a2 2 0 1 0 4 0v-1a2 2 0 1 0-4 0v1M20.732 12A2 2 0 0 0 17 13v1a2 2 0 0 0 3.726 1.01"/></svg>
    SVG;

    $this->assertSame($expected, $result);
});

test('it can add styles to icons', function () {
    $result = svg('tabler-abc', ['style' => 'color: #555'])->toHtml();

    $expected = <<<'SVG'
    <svg style="color: #555" class="tabler-icon" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path stroke="none" d="M0 0h24v24H0z"/><path d="M3 16v-6a2 2 0 1 1 4 0v6M3 13h4M10 8v6a2 2 0 1 0 4 0v-1a2 2 0 1 0-4 0v1M20.732 12A2 2 0 0 0 17 13v1a2 2 0 0 0 3.726 1.01"/></svg>
    SVG;

    $this->assertSame($expected, $result);
});
