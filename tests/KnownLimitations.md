# Known limitations (documented for the test suite)

## Half-bounded PHPStan requirements and merge information loss

When an extension declares a PHPStan requirement with only a lower bound (for example `>=1.0.0`) or only an upper bound (for example `<2.0.0`), that requirement is still registered in `EXTENSIONS` with `phpstanVersionConstraint` set to `null`, and it does not contribute to the merged `PHPSTAN_VERSION_CONSTRAINT`.

If another installed extension contributes a bounded interval, the merged constraint reflects only the bounded intervals. The half-bounded requirement does not tighten or widen that merge in a way that captures the full semantic intersection. Composer constraint strings can express half-bounded requirements; the information loss here is a choice in the current implementation (unbounded bounds are omitted from the merge), not a limitation of the representation format.

## Shared `GeneratedConfig.php` during tests

The suite writes the real `src/GeneratedConfig.php` and restores the committed stub after each test. Do not run PHPUnit with parallel workers (`--parallel` / multiple processes), or workers will race on that file.
