<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests;

use PHPStan\ExtensionInstaller\Tests\Support\PluginHarness;

final class GeneratedConfigFormatTest extends TestCase
{

	public function testGeneratedFileIsValidPhpDefiningTheThreeConstants(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/ext',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			])
			->runProcess();

		$contents = file_get_contents($this->generatedConfigPath);
		self::assertNotFalse($contents);
		self::assertStringContainsString('final class GeneratedConfig', $contents);
		self::assertStringContainsString('public const EXTENSIONS', $contents);
		self::assertStringContainsString('public const NOT_INSTALLED', $contents);
		self::assertStringContainsString('public const PHPSTAN_VERSION_CONSTRAINT', $contents);

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('acme/ext', $config['EXTENSIONS']);
	}

	public function testExtensionEntryContainsExpectedKeysAndFullExtraMap(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/rich',
				'type' => 'phpstan-extension',
				'version' => '3.4.5',
				'extra' => [
					'phpstan' => [
						'includes' => ['extension.neon'],
						'custom' => 'value',
					],
				],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$entry = $config['EXTENSIONS']['acme/rich'];

		self::assertSame(
			['install_path', 'relative_install_path', 'extra', 'version', 'phpstanVersionConstraint'],
			array_keys($entry)
		);
		self::assertSame(
			[
				'includes' => ['extension.neon'],
				'custom' => 'value',
			],
			$entry['extra']
		);
	}

}
