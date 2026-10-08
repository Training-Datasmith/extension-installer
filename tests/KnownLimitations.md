# Known limitations (documented for the test suite)

## Half-bounded PHPStan requirements and merge information loss

When an extension declares a PHPStan requirement with only a lower bound (for example `>=1.0.0`) or only an upper bound (for example `<2.0.0`), that requirement is still registered in `EXTENSIONS` with `phpstanVersionConstraint` set to `null`, and it does not contribute to the merged `PHPSTAN_VERSION_CONSTRAINT`.

If another installed extension contributes a bounded interval, the merged constraint reflects only the bounded intervals. The half-bounded requirement does not tighten or widen that merge in a way that captures the full semantic intersection. This is accepted behavior; fixing it would require a richer constraint representation than a single Composer constraint string.
