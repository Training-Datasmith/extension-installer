<?php declare(strict_types = 1);

$generatedConfigPath = dirname(__DIR__) . '/src/GeneratedConfig.php';
if (!is_file($generatedConfigPath)) {
    fwrite(STDERR, "Generated config file is missing.\n");
    exit(1);
}

require $generatedConfigPath;

echo json_encode([
    'EXTENSIONS' => \PHPStan\ExtensionInstaller\GeneratedConfig::EXTENSIONS,
    'NOT_INSTALLED' => \PHPStan\ExtensionInstaller\GeneratedConfig::NOT_INSTALLED,
    'PHPSTAN_VERSION_CONSTRAINT' => \PHPStan\ExtensionInstaller\GeneratedConfig::PHPSTAN_VERSION_CONSTRAINT,
], JSON_THROW_ON_ERROR);
