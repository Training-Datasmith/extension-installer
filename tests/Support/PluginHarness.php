<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests\Support;

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Installer\InstallationManager;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use PHPStan\ExtensionInstaller\Plugin;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use function array_merge;
use function array_key_exists;

final class PluginHarness
{

	/** @var string */
	private $projectRoot;

	/** @var ArrayLoader */
	private $loader;

	/** @var InstalledArrayRepository */
	private $localRepository;

	/** @var array<string, string|null> */
	private $installPaths = [];

	/** @var array<string, mixed> */
	private $rootPackageData = [
		'name' => 'vendor/project',
		'version' => '1.0.0',
		'type' => 'project',
	];

	/** @var PhpUnitTestCase */
	private $mockTestCase;

	public function __construct(string $projectRoot)
	{
		$this->projectRoot = $projectRoot;
		$this->loader = new ArrayLoader();
		$this->localRepository = new InstalledArrayRepository();
		$this->mockTestCase = new class extends PhpUnitTestCase {
		};
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public function withRootExtra(array $extra): self
	{
		$this->rootPackageData['extra'] = array_merge(
			$this->rootPackageData['extra'] ?? [],
			$extra
		);

		return $this;
	}

	/**
	 * @param array<string, mixed> $packageData
	 */
	public function addPackage(array $packageData, ?string $installPath = 'vendor/example/package'): self
	{
		$package = $this->loader->load($packageData);
		$this->localRepository->addPackage($package);
		$this->installPaths[$package->getName()] = $installPath;

		return $this;
	}

	/**
	 * @return array{io: BufferIO, config: array{EXTENSIONS: array<string, mixed>, NOT_INSTALLED: array<string, string>, PHPSTAN_VERSION_CONSTRAINT: string|null}}
	 */
	public function runProcess(): array
	{
		$rootPackage = new RootPackage($this->rootPackageData['name'], '1.0.0', '1.0.0');
		if (isset($this->rootPackageData['extra'])) {
			$rootPackage->setExtra($this->rootPackageData['extra']);
		}

		$composer = new Composer();
		$composer->setPackage($rootPackage);

		$repositoryManager = $this->createRepositoryManagerStub();
		$composer->setRepositoryManager($repositoryManager);

		$composer->setInstallationManager($this->createInstallationManagerMock());

		$io = new BufferIO();
		$event = new Event(ScriptEvents::POST_INSTALL_CMD, $composer, $io);

		$plugin = new Plugin();
		$plugin->process($event);

		return [
			'io' => $io,
		];
	}

	private function createRepositoryManagerStub(): RepositoryManager
	{
		$stub = $this->mockTestCase->getMockBuilder(RepositoryManager::class)
			->disableOriginalConstructor()
			->onlyMethods(['getLocalRepository'])
			->getMock();
		$stub->method('getLocalRepository')->willReturn($this->localRepository);

		return $stub;
	}

	private function createInstallationManagerMock(): InstallationManager
	{
		$paths = $this->installPaths;
		$installationManager = $this->mockTestCase->getMockBuilder(InstallationManager::class)
			->disableOriginalConstructor()
			->onlyMethods(['getInstallPath'])
			->getMock();
		$installationManager->method('getInstallPath')->willReturnCallback(
			static function ($package) use ($paths) {
				if (!$package instanceof PackageInterface) {
					return null;
				}

				if (!array_key_exists($package->getName(), $paths)) {
					return null;
				}

				return $paths[$package->getName()];
			}
		);

		return $installationManager;
	}

}
