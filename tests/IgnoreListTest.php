<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests;

use PHPStan\ExtensionInstaller\Tests\Support\PluginHarness;

final class IgnoreListTest extends TestCase
{

	public function testIgnoredExtensionIsExcludedAndReported(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->withRootExtra([
				'phpstan/extension-installer' => [
					'ignore' => ['acme/hidden'],
				],
			])
			->addPackage([
				'name' => 'acme/hidden',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayNotHasKey('acme/hidden', $config['EXTENSIONS']);
		self::assertArrayNotHasKey('acme/hidden', $config['NOT_INSTALLED']);
		self::assertStringContainsString('> acme/hidden: ignored', $result['io']->getOutput());
	}

	public function testReadmeIgnoreExampleUsesIgnoredStatus(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->withRootExtra([
				'phpstan/extension-installer' => [
					'ignore' => ['phpstan/phpstan-phpunit'],
				],
			])
			->addPackage([
				'name' => 'phpstan/phpstan-phpunit',
				'type' => 'phpstan-extension',
				'version' => '2.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayNotHasKey('phpstan/phpstan-phpunit', $config['EXTENSIONS']);
		self::assertArrayNotHasKey('phpstan/phpstan-phpunit', $config['NOT_INSTALLED']);
		self::assertStringContainsString('> phpstan/phpstan-phpunit: ignored', $result['io']->getOutput());
	}

	public function testIgnoredNamesAreSortedInOutput(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->withRootExtra([
				'phpstan/extension-installer' => [
					'ignore' => ['zebra/hidden', 'acme/hidden'],
				],
			])
			->addPackage([
				'name' => 'zebra/hidden',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['z.neon']]],
			])
			->addPackage([
				'name' => 'acme/hidden',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['a.neon']]],
			])
			->runProcess();

		$output = $result['io']->getOutput();
		self::assertLessThan(
			strpos($output, 'zebra/hidden: ignored'),
			strpos($output, 'acme/hidden: ignored')
		);
	}

	public function testMissingIgnoreKeyInstallsTheExtension(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/visible',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('acme/visible', $config['EXTENSIONS']);
	}

	public function testIgnoreListDoesNotRelabelNonExtensions(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->withRootExtra([
				'phpstan/extension-installer' => [
					'ignore' => ['phpstan/legacy-tool'],
				],
			])
			->addPackage([
				'name' => 'phpstan/legacy-tool',
				'type' => 'library',
				'version' => '1.0.0',
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('phpstan/legacy-tool', $config['NOT_INSTALLED']);
		self::assertStringContainsString('> phpstan/legacy-tool: not supported', $result['io']->getOutput());
		self::assertStringNotContainsString('ignored', $result['io']->getOutput());
	}

}
