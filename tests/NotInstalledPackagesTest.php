<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests;

use PHPStan\ExtensionInstaller\Tests\Support\PluginHarness;

final class NotInstalledPackagesTest extends TestCase
{

	public function testNameContainingPhpstanWithoutExtensionMetadataIsNotSupported(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'phpstan/legacy',
				'type' => 'library',
				'version' => '1.5.6',
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertSame('1.5.6', $config['NOT_INSTALLED']['phpstan/legacy']);
		self::assertStringContainsString('> phpstan/legacy: not supported', $result['io']->getOutput());
	}

	public function testCorePackagesAreExcluded(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'phpstan/phpstan',
				'type' => 'library',
				'version' => '2.1.0',
			])
			->addPackage([
				'name' => 'phpstan/phpstan-shim',
				'type' => 'library',
				'version' => '1.0.0',
			])
			->addPackage([
				'name' => 'phpstan/phpdoc-parser',
				'type' => 'library',
				'version' => '2.0.0',
			])
			->addPackage([
				'name' => 'phpstan/extension-installer',
				'type' => 'composer-plugin',
				'version' => '1.4.0',
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertSame([], $config['NOT_INSTALLED']);
		self::assertStringNotContainsString('not supported', $result['io']->getOutput());
	}

	public function testRealExtensionWhoseNameContainsPhpstanIsInstalled(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'phpstan/phpstan-phpunit',
				'type' => 'phpstan-extension',
				'version' => '2.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('phpstan/phpstan-phpunit', $config['EXTENSIONS']);
		self::assertArrayNotHasKey('phpstan/phpstan-phpunit', $config['NOT_INSTALLED']);
	}

	public function testNotSupportedLinesAreSorted(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'phpstan/zebra',
				'type' => 'library',
				'version' => '1.0.0',
			])
			->addPackage([
				'name' => 'phpstan/acme',
				'type' => 'library',
				'version' => '1.0.0',
			])
			->runProcess();

		$output = $result['io']->getOutput();
		self::assertLessThan(
			strpos($output, 'phpstan/zebra: not supported'),
			strpos($output, 'phpstan/acme: not supported')
		);
	}

}
