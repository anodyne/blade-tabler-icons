<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$resolve = static fn (string $path): string => str_starts_with($path, DIRECTORY_SEPARATOR)
    ? $path : $root.DIRECTORY_SEPARATOR.$path;
$inputDirectory = $resolve($argv[1] ?? 'resources/svg');
$svgDirectory = realpath($inputDirectory);
$outputFile = $resolve($argv[2] ?? 'src/Tabler.php');

if ($svgDirectory === false || ! is_dir($svgDirectory)) {
    fwrite(STDERR, "SVG directory does not exist: {$inputDirectory}\n");
    exit(1);
}

$iconFiles = glob($svgDirectory.DIRECTORY_SEPARATOR.'*.svg');
if ($iconFiles === false || $iconFiles === []) {
    fwrite(STDERR, "Failed to read SVG directory: {$svgDirectory}\n");
    exit(1);
}

sort($iconFiles, SORT_STRING);

$cases = [];
foreach ($iconFiles as $iconFile) {
    $iconName = pathinfo($iconFile, PATHINFO_FILENAME);
    if (! preg_match('/^[a-zA-Z0-9_-]+$/D', $iconName)) {
        throw new RuntimeException("Invalid icon filename: {$iconName}");
    }
    $document = new DOMDocument;
    if (! $document->load($iconFile, LIBXML_NONET) || $document->doctype !== null
        || $document->documentElement->localName !== 'svg'
        || $document->documentElement->namespaceURI !== 'http://www.w3.org/2000/svg'
        || $document->documentElement->getAttribute('viewBox') !== '0 0 24 24') {
        throw new RuntimeException("Invalid Tabler SVG: {$iconFile}");
    }
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
    if ($xpath->query('//svg:path[string-length(@d) > 0 and not(@stroke="none") and not(@fill="none")] | //svg:circle[@r > 0] | //svg:ellipse[@rx > 0] | //svg:rect[@width > 0] | //svg:line[@x1] | //svg:polyline[@points] | //svg:polygon[@points]')->length === 0) {
        throw new RuntimeException("SVG has no drawing content: {$iconFile}");
    }
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
    .implode("\n", $enumLines)
    ."\n}\n";

// Parse before atomically replacing the destination, including standalone runs.
token_get_all($content, TOKEN_PARSE);
$temporary = tempnam(dirname($outputFile), '.tabler-enum-');
if ($temporary === false) {
    throw new RuntimeException('Cannot create temporary enum file.');
}
try {
    if (file_put_contents($temporary, $content) === false || ! chmod($temporary, 0644)
        || ! rename($temporary, $outputFile)) {
        throw new RuntimeException("Failed to write enum file: {$outputFile}");
    }
} finally {
    if (is_file($temporary)) {
        unlink($temporary);
    }
}

fwrite(STDOUT, 'Wrote '.count($cases)." enum cases to {$outputFile}\n");

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

    // PHP allows keywords as enum cases except Class (the class-name constant).
    if (preg_match('/^[0-9]/', $caseName) === 1 || strtolower($caseName) === 'class') {
        $caseName = 'Icon'.$caseName;
    }

    return $caseName;
}
