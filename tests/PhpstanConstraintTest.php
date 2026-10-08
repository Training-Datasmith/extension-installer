<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests;

use Composer\Semver\Semver;
use PHPStan\ExtensionInstaller\Tests\Support\PluginHarness;

final class PhpstanConstraintTest extends TestCase
{

	public function testPackageWithoutPhpstanRequirementHasNullConstraint(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/no-req',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertNull($config['EXTENSIONS']['acme/no-req']['phpstanVersionConstraint']);
		self::assertNull($config['PHPSTAN_VERSION_CONSTRAINT']);
	}

	public function testBoundedConstraintIsMerged(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/bounded',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
				'require' => [
					'phpstan/phpstan' => '>=1.8.0 <2.0.0',
				],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$constraint = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($constraint);
		self::assertSame('>=1.8.0.0-dev, <2.0.0.0-dev', $constraint);
		self::assertSame('>=1.8.0.0-dev, <2.0.0.0-dev', $config['EXTENSIONS']['acme/bounded']['phpstanVersionConstraint']);

		self::assertTrue(Semver::satisfies('1.9.0', $constraint));
		self::assertFalse(Semver::satisfies('1.7.0', $constraint));
		self::assertFalse(Semver::satisfies('2.0.0', $constraint));
	}

	public function testOverlappingConstraintsIntersect(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/a',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['a.neon']]],
				'require' => ['phpstan/phpstan' => '>=1.0.0 <2.0.0'],
			])
			->addPackage([
				'name' => 'acme/b',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['b.neon']]],
				'require' => ['phpstan/phpstan' => '>=1.5.0 <3.0.0'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$constraint = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($constraint);

		self::assertTrue(Semver::satisfies('1.5.0', $constraint));
		self::assertTrue(Semver::satisfies('1.9.9', $constraint));
		self::assertFalse(Semver::satisfies('1.4.9', $constraint));
		self::assertFalse(Semver::satisfies('2.0.0', $constraint));
	}

	public function testContiguousOrStillCoversBothBranches(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/or-contiguous',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
				'require' => ['phpstan/phpstan' => '^1.0 || ^2.0'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$constraint = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($constraint);

		self::assertTrue(Semver::satisfies('1.5.0', $constraint));
		self::assertTrue(Semver::satisfies('2.5.0', $constraint));
	}

	public function testDisjunctiveGapIsNotFilled(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/gap',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
				'require' => ['phpstan/phpstan' => '^1.0 || ^3.0'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$packageConstraint = $config['EXTENSIONS']['acme/gap']['phpstanVersionConstraint'];
		$mergedConstraint = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($packageConstraint);
		self::assertNotNull($mergedConstraint);

		self::assertTrue(Semver::satisfies('1.5.0', $packageConstraint));
		self::assertTrue(Semver::satisfies('3.5.0', $packageConstraint));
		self::assertFalse(Semver::satisfies('2.5.0', $packageConstraint));

		self::assertTrue(Semver::satisfies('1.5.0', $mergedConstraint));
		self::assertTrue(Semver::satisfies('3.5.0', $mergedConstraint));
		self::assertFalse(Semver::satisfies('2.5.0', $mergedConstraint));
	}

	public function testOrConstraintMergeWithBoundedPartner(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/gap',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['gap.neon']]],
				'require' => ['phpstan/phpstan' => '^1.0 || ^3.0'],
			])
			->addPackage([
				'name' => 'acme/window',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['window.neon']]],
				'require' => ['phpstan/phpstan' => '>=1.5.0 <3.5.0'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$constraint = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($constraint);

		self::assertTrue(Semver::satisfies('1.5.0', $constraint));
		self::assertTrue(Semver::satisfies('3.2.0', $constraint));
		self::assertFalse(Semver::satisfies('2.5.0', $constraint));
		self::assertFalse(Semver::satisfies('3.6.0', $constraint));
	}

	public function testDisjointConstraintsMatchNothing(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/one',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['one.neon']]],
				'require' => ['phpstan/phpstan' => '>=1.0.0 <2.0.0'],
			])
			->addPackage([
				'name' => 'acme/two',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['two.neon']]],
				'require' => ['phpstan/phpstan' => '>=3.0.0 <4.0.0'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$constraint = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($constraint);

		self::assertFalse(Semver::satisfies('1.5.0', $constraint));
		self::assertFalse(Semver::satisfies('3.5.0', $constraint));
		self::assertArrayHasKey('acme/one', $config['EXTENSIONS']);
		self::assertArrayHasKey('acme/two', $config['EXTENSIONS']);
	}

	public function testNotEqualHoleInConstraint(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/hole',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
				'require' => ['phpstan/phpstan' => '^1.10, !=1.10.5'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$constraint = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($constraint);

		self::assertTrue(Semver::satisfies('1.10.4', $constraint));
		self::assertFalse(Semver::satisfies('1.10.5', $constraint));
		self::assertTrue(Semver::satisfies('1.10.6', $constraint));
	}

	public function testZeroLowerBoundStillInstalls(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/upper-only',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
				'require' => ['phpstan/phpstan' => '<2.0.0'],
			])
			->addPackage([
				'name' => 'acme/bounded',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['bounded.neon']]],
				'require' => ['phpstan/phpstan' => '>=1.8.0 <2.0.0'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('acme/upper-only', $config['EXTENSIONS']);
		self::assertNull($config['EXTENSIONS']['acme/upper-only']['phpstanVersionConstraint']);

		$merged = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($merged);
		self::assertSame('>=1.8.0.0-dev, <2.0.0.0-dev', $merged);
	}

	public function testInfiniteUpperBoundStillInstalls(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/lower-only',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
				'require' => ['phpstan/phpstan' => '>=1.0.0'],
			])
			->addPackage([
				'name' => 'acme/bounded',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['bounded.neon']]],
				'require' => ['phpstan/phpstan' => '>=1.8.0 <2.0.0'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('acme/lower-only', $config['EXTENSIONS']);
		self::assertNull($config['EXTENSIONS']['acme/lower-only']['phpstanVersionConstraint']);

		$merged = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($merged);
		self::assertSame('>=1.8.0.0-dev, <2.0.0.0-dev', $merged);
	}

	public function testStarConstraintStillInstalls(): void
	{
		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/star',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
				'require' => ['phpstan/phpstan' => '*'],
			])
			->addPackage([
				'name' => 'acme/bounded',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['bounded.neon']]],
				'require' => ['phpstan/phpstan' => '>=1.8.0 <2.0.0'],
			])
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayHasKey('acme/star', $config['EXTENSIONS']);
		self::assertNull($config['EXTENSIONS']['acme/star']['phpstanVersionConstraint']);

		$merged = $config['PHPSTAN_VERSION_CONSTRAINT'];
		self::assertNotNull($merged);
		self::assertSame('>=1.8.0.0-dev, <2.0.0.0-dev', $merged);
	}

}
