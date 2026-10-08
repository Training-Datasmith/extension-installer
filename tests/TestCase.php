<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests;

use Composer\Semver\Intervals;
use PHPStan\ExtensionInstaller\GeneratedConfig;
use PHPUnit\Framework\TestCase as BaseTestCase;
use function class_exists;
use function dirname;
use function file_put_contents;
use function getcwd;
use function is_array;
use function is_resource;
use function json_decode;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function stream_get_contents;
use const DIRECTORY_SEPARATOR;

abstract class TestCase extends BaseTestCase
{

	/** @var string */
	protected $projectRoot;

	/** @var string */
	protected $generatedConfigPath;

	/** @var string|false */
	protected $previousWorkingDirectory = false;

	protected function setUp(): void
	{
		parent::setUp();

		$this->projectRoot = dirname(__DIR__);
		$this->generatedConfigPath = $this->projectRoot . '/src/GeneratedConfig.php';
		$this->previousWorkingDirectory = getcwd();
	}

	protected function tearDown(): void
	{
		$this->restoreGeneratedConfigStub();

		if ($this->previousWorkingDirectory !== false && getcwd() !== $this->previousWorkingDirectory) {
			chdir($this->previousWorkingDirectory);
		}

		Intervals::clear();

		self::assertFalse(
			class_exists(GeneratedConfig::class, false),
			'GeneratedConfig must not be loaded in the parent process.'
		);

		parent::tearDown();
	}

	protected function restoreGeneratedConfigStub(): void
	{
		if (!isset($GLOBALS['phpstan_extension_installer_generated_config_stub'])) {
			return;
		}

		file_put_contents(
			$this->generatedConfigPath,
			$GLOBALS['phpstan_extension_installer_generated_config_stub']
		);
	}

	/**
	 * @return array{EXTENSIONS: array<string, mixed>, NOT_INSTALLED: array<string, string>, PHPSTAN_VERSION_CONSTRAINT: string|null}
	 */
	protected function readGeneratedConfigFromSubprocess(): array
	{
		$command = [
			PHP_BINARY,
			$this->projectRoot . '/tests/read-generated-config.php',
		];

		$descriptorSpec = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open($command, $descriptorSpec, $pipes, $this->projectRoot);
		self::assertIsResource($process);

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$exitCode = proc_close($process);
		self::assertSame(0, $exitCode, 'Subprocess exit code must be 0, stderr: ' . $stderr);
		self::assertSame('', $stderr, 'Subprocess stderr must be empty.');

		/** @var array{EXTENSIONS: array<string, mixed>, NOT_INSTALLED: array<string, string>, PHPSTAN_VERSION_CONSTRAINT: string|null} $decoded */
		$decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
		self::assertIsArray($decoded);

		return $decoded;
	}

	protected function makeRepoRelativePath(string $relativePath): string
	{
		return $this->projectRoot . DIRECTORY_SEPARATOR . $relativePath;
	}

}
