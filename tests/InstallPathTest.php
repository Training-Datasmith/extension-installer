<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests;

use PHPStan\ExtensionInstaller\Tests\Support\PluginHarness;
use function is_dir;
use function mkdir;
use function scandir;
use function rmdir;
use function unlink;
use function realpath;
use function uniqid;
use const DIRECTORY_SEPARATOR;

final class InstallPathTest extends TestCase
{

	/** @var string|null */
	private $fixtureDirectory;

	protected function setUp(): void
	{
		parent::setUp();
		chdir($this->projectRoot);
	}

	protected function tearDown(): void
	{
		if ($this->fixtureDirectory !== null && is_dir($this->fixtureDirectory)) {
			$this->removeDirectory($this->fixtureDirectory);
		}

		parent::tearDown();
	}

	private function removeDirectory(string $directory): void
	{
		$entries = scandir($directory);
		if ($entries === false) {
			return;
		}

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $directory . DIRECTORY_SEPARATOR . $entry;
			if (is_dir($path)) {
				$this->removeDirectory($path);
				continue;
			}

			unlink($path);
		}

		rmdir($directory);
	}

	public function testRelativeInstallPathIsAnchoredToCwd(): void
	{
		$relativeInstallPath = 'tmp/phpunit-fixtures-' . uniqid('', true) . '/vendor/acme/ext';
		$this->fixtureDirectory = $this->projectRoot . DIRECTORY_SEPARATOR . dirname($relativeInstallPath, 2);
		mkdir($this->fixtureDirectory . DIRECTORY_SEPARATOR . 'vendor/acme/ext', 0777, true);

		chdir($this->projectRoot);

		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/ext',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			], $relativeInstallPath)
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$entry = $config['EXTENSIONS']['acme/ext'];

		$expectedAbsolute = getcwd() . DIRECTORY_SEPARATOR . $relativeInstallPath;
		self::assertSame($expectedAbsolute, $entry['install_path']);

		$generatedConfigDir = $this->projectRoot . '/src';
		$resolved = realpath($generatedConfigDir . '/' . $entry['relative_install_path']);
		self::assertSame(realpath($expectedAbsolute), $resolved);
	}

	public function testAbsoluteInstallPathIsStoredUnchanged(): void
	{
		$this->fixtureDirectory = $this->makeRepoRelativePath('tmp/phpunit-fixtures-' . uniqid('', true));
		mkdir($this->fixtureDirectory, 0777, true);

		chdir($this->projectRoot);

		(new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/abs',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			], $this->fixtureDirectory)
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		$entry = $config['EXTENSIONS']['acme/abs'];

		self::assertSame($this->fixtureDirectory, $entry['install_path']);

		$generatedConfigDir = $this->projectRoot . '/src';
		$resolved = realpath($generatedConfigDir . '/' . $entry['relative_install_path']);
		self::assertSame(realpath($this->fixtureDirectory), $resolved);
	}

	public function testNullInstallPathSkipsThePackage(): void
	{
		$result = (new PluginHarness($this->projectRoot))
			->addPackage([
				'name' => 'acme/missing',
				'type' => 'phpstan-extension',
				'version' => '1.0.0',
				'extra' => ['phpstan' => ['includes' => ['extension.neon']]],
			], null)
			->runProcess();

		$config = $this->readGeneratedConfigFromSubprocess();
		self::assertArrayNotHasKey('acme/missing', $config['EXTENSIONS']);
		self::assertStringNotContainsString('acme/missing: installed', $result['io']->getOutput());
	}

}
