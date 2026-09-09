<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function withEnumFixture(Closure $test): void
{
    $directory = sys_get_temp_dir().'/tabler-enum-test-'.bin2hex(random_bytes(8));
    mkdir($directory.'/svg', 0777, true);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h10v10z"/></svg>';
    $run = static function () use ($directory): Process {
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/bin/generate-enum.php', $directory.'/svg', $directory.'/Tabler.php'], sys_get_temp_dir());
        $process->run();

        return $process;
    };
    try {
        $test($directory, $svg, $run);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
}

test('standalone enum generation rejects invalid input without replacing output', function (string $scenario) {
    withEnumFixture(function ($directory, $svg, $run) use ($scenario) {
        file_put_contents($directory.'/Tabler.php', 'previous enum');
        $input = $directory.'/svg';
        if ($scenario === 'missing') {
            rmdir($input);
        } elseif ($scenario !== 'empty') {
            $content = match ($scenario) {
                'malformed XML' => '<svg>',
                'wrong root' => str_replace(['<svg ', '</svg>'], ['<html ', '</html>'], $svg),
                'wrong namespace' => str_replace('http://www.w3.org/2000/svg', 'urn:invalid', $svg),
                'wrong viewBox' => str_replace('0 0 24 24', '0 0 16 16', $svg),
                'doctype' => '<!DOCTYPE svg>'.$svg,
                'empty drawing' => str_replace('M1 1h10v10z', '', $svg),
                'invisible placeholder' => str_replace('<path ', '<path stroke="none" fill="none" ', $svg),
                default => $svg,
            };
            $filename = $scenario === 'invalid filename' ? "bad'name" : ($scenario === 'empty case' ? '---' : 'a-b');
            file_put_contents($input.'/'.$filename.'.svg', $content);
            if ($scenario === 'case collision') {
                file_put_contents($input.'/a--b.svg', $svg);
            }
        }
        expect($run()->isSuccessful())->toBeFalse()
            ->and(file_get_contents($directory.'/Tabler.php'))->toBe('previous enum')
            ->and(glob($directory.'/.tabler-enum-*'))->toBe([]);
    });
})->with(['missing', 'empty', 'malformed XML', 'wrong root', 'wrong namespace', 'wrong viewBox', 'doctype', 'empty drawing', 'invisible placeholder', 'invalid filename', 'empty case', 'case collision']);

test('standalone generation is deterministic and produces loadable enum mappings', function () {
    withEnumFixture(function ($directory, $svg, $run) {
        foreach (['zebra', 'class', '123', 'a-b-2', 'brand-github', 'function', 'heart-filled'] as $name) {
            file_put_contents($directory.'/svg/'.$name.'.svg', $svg);
        }
        expect($run()->isSuccessful())->toBeTrue();
        $first = file_get_contents($directory.'/Tabler.php');
        expect($run()->isSuccessful())->toBeTrue()
            ->and(file_get_contents($directory.'/Tabler.php'))->toBe($first);
        $read = new Process([PHP_BINARY, '-r', 'require $argv[1]; foreach (Anodyne\\TablerIcons\\Tabler::cases() as $case) { echo $case->name."=".$case->value."\n"; }', $directory.'/Tabler.php']);
        $read->run();
        expect($read->isSuccessful())->toBeTrue()
            ->and(explode("\n", trim($read->getOutput())))->toBe([
                'Icon123=tabler-123', 'AB2=tabler-a-b-2', 'BrandGithub=tabler-brand-github',
                'IconClass=tabler-class', 'Function=tabler-function', 'HeartFilled=tabler-heart-filled', 'Zebra=tabler-zebra',
            ]);
    });
});

test('standalone generation cleans up when the destination cannot be replaced', function () {
    withEnumFixture(function ($directory, $svg, $run) {
        file_put_contents($directory.'/svg/abc.svg', $svg);
        mkdir($directory.'/Tabler.php');
        file_put_contents($directory.'/Tabler.php/keep.txt', 'keep');
        expect($run()->isSuccessful())->toBeFalse()
            ->and(file_get_contents($directory.'/Tabler.php/keep.txt'))->toBe('keep')
            ->and(glob($directory.'/.tabler-enum-*'))->toBe([]);
    });
});
