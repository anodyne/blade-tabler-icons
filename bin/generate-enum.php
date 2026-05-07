<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php bin/generate-enum.php <svg-directory> <output-file>\n");
    exit(1);
}

$svgDirectory = realpath($argv[1]);
$outputFile = $argv[2];

if ($svgDirectory === false || !is_dir($svgDirectory)) {
    fwrite(STDERR, "SVG directory does not exist: {$argv[1]}\n");
    exit(1);
}

$iconFiles = glob($svgDirectory . DIRECTORY_SEPARATOR . '*.svg');
if ($iconFiles === false) {
    fwrite(STDERR, "Failed to read SVG directory: {$svgDirectory}\n");
    exit(1);
}

sort($iconFiles, SORT_STRING);

$cases = [];
foreach ($iconFiles as $iconFile) {
    $iconName = pathinfo($iconFile, PATHINFO_FILENAME);
    $caseName = iconNameToCaseName($iconName);

    if (isset($cases[$caseName])) {
        fwrite(
            STDERR,
            "Duplicate enum case generated for '{$iconName}' and '{$cases[$caseName]}': {$caseName}\n"
        );
        exit(1);
    }

    $cases[$caseName] = $iconName;
}

$enumLines = [];
foreach ($cases as $caseName => $iconName) {
    $enumLines[] = "    case {$caseName} = 'tabler-{$iconName}';";
}

$content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Anodyne\\TablerIcons;\n\nenum Tabler: string\n{\n"
    . implode("\n", $enumLines)
    . "\n}\n";

if (file_put_contents($outputFile, $content) === false) {
    fwrite(STDERR, "Failed to write enum file: {$outputFile}\n");
    exit(1);
}

fwrite(STDOUT, "Wrote " . count($cases) . " enum cases to {$outputFile}\n");

function iconNameToCaseName(string $iconName): string
{
    $parts = preg_split('/-+/', $iconName) ?: [];
    $caseName = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $caseName .= ucfirst($part);
    }

    $caseName = preg_replace('/[^A-Za-z0-9_]/', '', $caseName) ?? '';

    if ($caseName === '') {
        throw new RuntimeException("Unable to create enum case name from icon '{$iconName}'.");
    }

    if (preg_match('/^[0-9]/', $caseName) === 1) {
        $caseName = 'Icon' . $caseName;
    }

    return $caseName;
}
