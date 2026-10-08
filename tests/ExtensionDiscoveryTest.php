<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests;

use PHPStan\ExtensionInstaller\Tests\Support\PluginHarness;

final class ExtensionDiscoveryTest extends TestCase
{

	public function testExtensionTypeRecordsExtraIncludesAndVersion(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/ext',
				'type' => 'phpstan-extension',
				'version' => '1.2.3',
				'extra' => [
					'phpstan' => [
						'includes' => ['extension.neon'],
					],
				],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('acme/ext', $config['EXTENSIONS']);
		self::assertSame(
			['includes' => ['extension.neon']],
			$config['EXTENSIONS']['acme/ext']['extra']
		);
		self::assertSame('1.2.3', $config['EXTENSIONS']['acme/ext']['version']);
	}

	public function testExtraPhpstanWithoutExtensionTypeIsInstalled(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/library-ext',
				'type' => 'library',
				'version' => '2.0.0',
				'extra' => [
					'phpstan' => [
						'includes' => ['rules.neon'],
					],
				],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('acme/library-ext', $config['EXTENSIONS']);
	}

	public function testExtensionTypeWithoutExtraStoresNullExtra(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/bare',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertNull($config['EXTENSIONS']['acme/bare']['extra']);
	}

	public function testUnrelatedPackageIsOmitted(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/unrelated',
				'type' => 'library',
				'version' => '1.0.0',
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertSame([], $config['EXTENSIONS']);
		self::assertSame([], $config['NOT_INSTALLED']);
		self::assertStringNotContainsString('> acme/unrelated: installed', $result['io']->getOutput());
	}

	public function testExtensionsAreSortedByPackageName(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'zebra/ext',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['a.neon']]],
			])
			->addPackage([
				'name' => 'acme/ext',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['b.neon']]],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertSame(['acme/ext', 'zebra/ext'], array_keys($config['EXTENSIONS']));

		$output = $result['io']->getOutput();
		self::assertLessThan(
			strpos($output, 'zebra/ext'),
			strpos($output, 'acme/ext')
		);
	}

	public function testEmptyRepositoryWritesEmptyArraysAndNullConstraint(): void
	{
		$result = (new PluginHarness($this->projectRoot))->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertSame([], $config['EXTENSIONS']);
		self::assertSame([], $config['NOT_INSTALLED']);
		self::assertNull($config['PHPSTAN_VERSION_CONSTRAINT']);
		self::assertStringContainsString('Extensions installed', $result['io']->getOutput());
	}

	public function testUnchangedSecondRunPrintsSummaryOnly(): void
	{
		$harness = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/ext',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			]);

		$first = $harness->runProcess();
		self::assertStringContainsString('acme/ext: installed', $first['io']->getOutput());

		$second = $harness->runProcess();
		self::assertStringContainsString('Extensions installed', $second['io']->getOutput());
		self::assertStringNotContainsString('> acme/ext: installed', $second['io']->getOutput());
		self::assertStringNotContainsString('not supported', $second['io']->getOutput());
		self::assertStringNotContainsString('ignored', $second['io']->getOutput());
	}

	public function testRepositoryChangePrintsPerPackageLinesAgain(): void
	{
		$harness = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/first',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['a.neon']]],
			]);

		$harness->runProcess();

		$harness = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/second',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['b.neon']]],
			]);

		$second = $harness->runProcess();
		self::assertStringContainsString('acme/second: installed', $second['io']->getOutput());
	}

}
