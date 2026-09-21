<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Build;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * What the package ships, checked from the source tree.
 *
 * Regression tests for the packaging findings of the 2026-09 audit: the _JEXEC guard on every shipped
 * PHP file, the vendor folder's deny-all files and excluded development files (L11), the stale second
 * manifest (I11); plus manifests that agree with the files on disk.
 *
 * @since 4.1.0
 */
class PackageSurfaceTest extends TestCase
{
	/**
	 * Every shipped PHP file outside vendor/.
	 *
	 * @return  array<string, array{0: string}>
	 * @since   4.1.0
	 */
	public static function shippedPhpFiles(): array
	{
		$root  = self::root();
		$cases = [];

		foreach (['component', 'plugins'] as $dir)
		{
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));

			foreach ($iterator as $file)
			{
				if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/vendor/'))
				{
					continue;
				}

				$cases[substr($file->getPathname(), strlen($root) + 1)] = [$file->getPathname()];
			}
		}

		ksort($cases);

		return $cases;
	}

	/**
	 * Every shipped PHP file refuses direct web access.
	 *
	 * @param   string  $path  The file.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('shippedPhpFiles')]
	public function testJexecGuard(string $path): void
	{
		$this->assertMatchesRegularExpression(
			'/defined\s*\(\s*[\'"]_JEXEC[\'"]\s*\)\s*(or|\|\|)\s*die/i',
			(string) file_get_contents($path),
			'No `defined(\'_JEXEC\') or die` guard.'
		);
	}

	/**
	 * L11: the vendor folder denies all web access, and build.xml keeps the libraries' development
	 * files and the gitignored development manifest (I11) out of the package.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testVendorFolderAndBuildExclusions(): void
	{
		$root = self::root();

		$this->assertFileExists($root . '/component/backend/vendor/.htaccess');
		$this->assertFileExists($root . '/component/backend/vendor/web.config');
		$this->assertMatchesRegularExpression('/Require\s+all\s+denied|Deny\s+from\s+all/i', file_get_contents($root . '/component/backend/vendor/.htaccess'));
		// IIS: either a deny-all authorisation rule, or request filtering that allows no file extension.
		$this->assertMatchesRegularExpression(
			'/<deny\s+users="\*"|<fileExtensions\s+allowUnlisted="false"/i',
			file_get_contents($root . '/component/backend/vendor/web.config')
		);

		$build = new SimpleXMLElement(file_get_contents($root . '/build.xml'));
		$fileset = $build->xpath('//fileset[@id="component"]')[0] ?? null;

		$this->assertNotNull($fileset, 'build.xml has no "component" fileset.');

		$excludes = array_map(fn($e) => (string) $e['name'], $fileset->xpath('exclude'));

		foreach (['backend/datacompliance.xml', 'backend/vendor/**/minitest/**', 'backend/vendor/**/composer.json', 'backend/vendor/composer/installed.json'] as $pattern)
		{
			$this->assertContains($pattern, $excludes, sprintf('build.xml does not exclude %s from the component package.', $pattern));
		}
	}

	/**
	 * The package manifest lists exactly the plugins in the source tree.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testPackageManifestListsEveryPlugin(): void
	{
		$root     = self::root();
		$manifest = new SimpleXMLElement(file_get_contents($root . '/pkg_datacompliance.xml'));
		$listed   = [];

		foreach ($manifest->files->file as $file)
		{
			if ((string) $file['type'] === 'plugin')
			{
				$listed[] = $file['group'] . '/' . $file['id'];
			}
		}

		$onDisk = array_map(
			fn(string $dir): string => basename(\dirname($dir)) . '/' . basename($dir),
			glob($root . '/plugins/*/*', GLOB_ONLYDIR)
		);

		sort($listed);
		sort($onDisk);

		$this->assertSame($onDisk, $listed);
	}

	/**
	 * Every file and folder a manifest names exists, and every folder the component's media directory
	 * has is installed.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testComponentManifestMatchesTheTree(): void
	{
		$root      = self::root();
		$component = $root . '/component';
		$manifest  = new SimpleXMLElement(file_get_contents($component . '/datacompliance.xml'));

		foreach ([['files', 'frontend'], ['administration/files', 'backend'], ['media', 'media']] as [$path, $default])
		{
			$node   = $manifest->xpath($path)[0];
			$folder = $component . '/' . ((string) $node['folder'] ?: $default);

			foreach ($node->folder as $item)
			{
				$this->assertDirectoryExists($folder . '/' . $item, sprintf('<%s> names a folder that does not exist.', $path));
			}

			foreach ($node->filename as $item)
			{
				$this->assertFileExists($folder . '/' . $item, sprintf('<%s> names a file that does not exist.', $path));
			}
		}

		$media    = $manifest->xpath('media')[0];
		$listed   = array_map('strval', iterator_to_array($media->folder, false));
		$unlisted = array_values(array_diff(array_map('basename', glob($component . '/media/*', GLOB_ONLYDIR)), $listed));

		$this->assertSame([], $unlisted, 'Media folders the manifest does not install.');
	}

	/**
	 * Every plugin manifest names only files and folders that exist, and declares the namespace its
	 * src/ classes use.
	 *
	 * @return  array<string, array{0: string}>
	 * @since   4.1.0
	 */
	public static function pluginDirectories(): array
	{
		$cases = [];

		foreach (glob(self::root() . '/plugins/*/*', GLOB_ONLYDIR) as $dir)
		{
			$cases[basename(\dirname($dir)) . '/' . basename($dir)] = [$dir];
		}

		return $cases;
	}

	/**
	 * A plugin's manifest agrees with its files.
	 *
	 * @param   string  $dir  The plugin directory.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('pluginDirectories')]
	public function testPluginManifest(string $dir): void
	{
		$manifestFile = $dir . '/' . basename($dir) . '.xml';

		$this->assertFileExists($manifestFile);

		$manifest = new SimpleXMLElement(file_get_contents($manifestFile));

		foreach ($manifest->files->folder as $folder)
		{
			$this->assertDirectoryExists($dir . '/' . $folder);
		}

		foreach ($manifest->files->filename as $file)
		{
			$this->assertFileExists($dir . '/' . $file);
		}

		$namespace = (string) $manifest->namespace;

		$this->assertNotSame('', $namespace, 'The manifest declares no namespace.');

		foreach (glob($dir . '/src/Extension/*.php') as $class)
		{
			$this->assertMatchesRegularExpression(
				'/^namespace\s+' . preg_quote($namespace, '/') . '\\\\Extension;/m',
				file_get_contents($class),
				basename($class) . ' is not in the namespace the manifest declares.'
			);
		}
	}

	/**
	 * The repository root.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	private static function root(): string
	{
		return \dirname(__DIR__, 2);
	}
}
