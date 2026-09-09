# Changelog

This changelog follows [the Keep a Changelog standard](https://keepachangelog.com).

## Unreleased

- Migrate PHP tests to Pest and expand provider, rendering, publishing, and
  standalone enum generator regression coverage.
- Replace PHP CS Fixer with Laravel Pint for formatting and CI style checks.
- Pin Bun 1.3.14 with a committed lockfile and use SVGO 4.1.0.
- Replace destructive compilation with staged, validated SVG and enum generation,
  collision checks, and a deterministic rebuild check.
- Add SVG/enum integrity, Blade rendering, publishing, and build failure tests.
- Align CI with existing Laravel 9–13 support and document maintenance commands.
- Include the upstream Tabler artwork license.
- Verify Tabler 3.46.0 is still the latest v3 release: all 6,184 icon names and enum
  mappings remain unchanged, with no additions, removals, or public API changes.
  SVGO 4 normalizes a floating-point path coordinate in `sun-wind.svg`.

## 0.0.1 (2020-07-02)

Initial release.
