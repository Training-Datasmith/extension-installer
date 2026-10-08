<?php declare(strict_types = 1);

namespace PHPStan\ExtensionInstaller\Tests;

use Composer\Script\ScriptEvents;
use PHPStan\ExtensionInstaller\Plugin;

final class PluginLifecycleTest extends TestCase
{

	public function testSubscribedEvents(): void
	{
		self::assertSame(
			[
				ScriptEvents::POST_INSTALL_CMD => 'process',
				ScriptEvents::POST_UPDATE_CMD => 'process',
			],
			Plugin::getSubscribedEvents()
		);
	}

}
