<?php declare(strict_types = 1);

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($vendorAutoload)) {
    fwrite(STDERR, "Run composer install before executing the test suite.\n");
    exit(1);
}

require $vendorAutoload;

// The suite mutates src/GeneratedConfig.php; parallel PHPUnit workers would race on that path.
$generatedConfigPath = dirname(__DIR__) . '/src/GeneratedConfig.php';
$stubContents = file_get_contents($generatedConfigPath);
if ($stubContents === false) {
    fwrite(STDERR, "Could not read src/GeneratedConfig.php.\n");
    exit(1);
}

if (strpos($stubContents, 'public const EXTENSIONS = [];') === false) {
    fwrite(STDERR, "src/GeneratedConfig.php is not the committed stub; restore it before running tests.\n");
    exit(1);
}

$GLOBALS['phpstan_extension_installer_generated_config_stub'] = $stubContents;

register_shutdown_function(static function () use ($generatedConfigPath): void {
    if (!isset($GLOBALS['phpstan_extension_installer_generated_config_stub'])) {
        return;
    }

    file_put_contents($generatedConfigPath, $GLOBALS['phpstan_extension_installer_generated_config_stub']);
});
