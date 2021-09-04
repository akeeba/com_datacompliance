<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Model;

defined('_JEXEC') or die;

use DirectoryIterator;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\Adapter\PackageAdapter;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\MVC\Model\DatabaseAwareTrait;
use Joomla\CMS\Table\Extension;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class UpgradeModel extends BaseModel
{
	use DatabaseAwareTrait;

	/** @var string Relative directory to the custom handlers */
	private const CUSTOM_HANDLERS_DIRECTORY = 'UpgradeHandler';

	/** @var string[] Included extensions to automatically publish on a NEW INSTALLATION */
	private const ENABLE_EXTENSIONS = [
		'plg_datacompliance_joomla',
		'plg_system_datacompliance',
		'plg_user_datacompliance',
		'plg_console_datacompliance',
	];

	/**
	 * Name of the package being replaced
	 *
	 * @var   string
	 */
	private const OLD_PACKAGE_NAME = '';

	/**
	 * Name of the new package this component belongs to
	 *
	 * @var   string
	 */
	private const PACKAGE_NAME = 'pkg_datacompliance';

	/**
	 * Criteria for determining this is the Pro version by inspecting the filesystem.
	 *
	 * Each array element is an array in itself with two elements:
	 * * 0: const|file|folder
	 * * 1: constant name; or path to the file or folder to check for existence
	 *
	 * Matching any criterion means we have the Pro version
	 *
	 * @var   array
	 */
	private const PRO_CRITERIA = [];

	/** @var string[] Extensions to always uninstall if they are still installed (runs on install and upgrade) */
	private const REMOVE_EXTENSIONS = [
		'file_datacompliance'
	];

	/**
	 * Files and folders to remove from both Core and Pro versions
	 *
	 * @var array[]
	 */
	private const REMOVE_FROM_ALL_VERSIONS = [
		'files'   => [
			// Old charting library
			'media/com_datacompliance/js/Chart.bundle.min.js',

			// FOF 4 stuff
			'administrator/components/com_datacompliance/datacompliance.php',
			'administrator/components/com_datacompliance/fof.xml',
			'administrator/components/com_datacompliance/version.php',

			'components/com_datacompliance/datacompliance.php',

			'cli/datacompliance_account_delete.php',
			'cli/datacompliance_account_lifecycle.php',
			'cli/datacompliance_audit_replay.php',
			'cli/datacompliance_lifecycle_notify.php',

		],
		'folders' => [
			// Old CLI base script
			'administrator/components/com_datacompliance/assets/cli',

			// FOF 3 stuff
			'administrator/components/com_datacompliance/ViewTemplates',
			'components/com_datacompliance/ViewTemplates',

			// FOF 4 stuff
			'administrator/components/com_datacompliance/Controller',
			'administrator/components/com_datacompliance/Dispatcher',
			'administrator/components/com_datacompliance/fields',
			'administrator/components/com_datacompliance/Helper',
			'administrator/components/com_datacompliance/Model',
			'administrator/components/com_datacompliance/Toolbar',
			'administrator/components/com_datacompliance/View',

			'components/com_datacompliance/Controller',
			'components/com_datacompliance/Dispatcher',
			'components/com_datacompliance/Model',
			'components/com_datacompliance/View',
		],
	];

	/**
	 * Files and folders to remove ONLY from the Core version
	 *
	 * @var array[]
	 */
	private const REMOVE_FROM_CORE = [
		'files'   => [
			// UNUSED IN THIS PACKAGE
		],
		'folders' => [
			// UNUSED IN THIS PACKAGE
		],
	];

	/**
	 * UpgradeModel custom handlers, implementing custom logic for each extension.
	 *
	 * @var object[]
	 */
	private $customHandlers = [];

	/**
	 * Caches the extension names to IDs so we don't query the database too many times.
	 *
	 * @var   array
	 */
	private $extensionIds = [];

	/**
	 * List of extensions included in both old and new packages (if applicable)
	 *
	 * @var   array
	 */
	private $extensionsList;

	public function __construct($config = [])
	{
		parent::__construct($config);

		// Set the main Joomla database object
		$this->setDbo(Factory::getContainer()->get(DatabaseDriver::class));

		// Find out the common extensions
		if ($this->isSamePackage())
		{
			$this->extensionsList = $this->getExtensionsFromPackage(self::PACKAGE_NAME);
		}
		else
		{
			$oldExtensions        = $this->getExtensionsFromPackage(self::OLD_PACKAGE_NAME);
			$newExtensions        = $this->getExtensionsFromPackage(self::PACKAGE_NAME);
			$this->extensionsList = array_intersect($newExtensions, $oldExtensions);
		}

		// Load extension-specific adapters
		$this->loadCustomHandlers();
	}

	/**
	 * Adopt the extensions by new package.
	 *
	 * This modifies the package_id column of the #__extensions table for the records of the extensions declared in the
	 * new package's manifest. This allows you to use Discover to install new extensions without leaving them “orphan”
	 * of a package in the #__extensions table, something which could cause problems when running Joomla! Update.
	 *
	 * @return  void
	 */
	public function adoptMyExtensions(): void
	{
		// Get the extension ID of the new package
		$newPackageId = $this->getExtensionId(self::PACKAGE_NAME);

		if (empty($newPackageId))
		{
			return;
		}

		// Get the extension IDs
		$extensionIDs = array_map([$this, 'getExtensionId'], $this->getExtensionsFromPackage(self::PACKAGE_NAME));
		$extensionIDs = array_filter($extensionIDs, function ($x) {
			return !empty($x);
		});

		if (empty($extensionIDs))
		{
			return;
		}

		/**
		 * Looks stupid? This realigns the integer keys because whereIn() expects 0-based, monotonically increasing
		 * array keys. Otherwise it ends up emitting null values. GROAN!
		 */
		$extensionIDs = array_merge($extensionIDs);

		// Reassign all extensions
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->qn('package_id') . ' = :package_id')
			->whereIn($db->qn('extension_id'), $extensionIDs, ParameterType::INTEGER)
			->bind(':package_id', $newPackageId, ParameterType::INTEGER);
		$db->setQuery($query)->execute();
	}

	/**
	 * Returns the extension ID for a Joomla extension given its name.
	 *
	 * This is deliberately public so that custom handlers can use it without having to reimplement it.
	 *
	 * @param   string  $extension  The extension name, e.g. `plg_system_example`.
	 *
	 * @return  int|null  The extension ID or null if no such extension exists
	 */
	public function getExtensionId(string $extension): ?int
	{
		if (isset($this->extensionIds[$extension]))
		{
			return $this->extensionIds[$extension];
		}

		$this->extensionIds[$extension] = null;

		$criteria = $this->extensionNameToCriteria($extension);

		if (empty($criteria))
		{
			return $this->extensionIds[$extension];
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('extension_id'))
			->from($db->quoteName('#__extensions'));

		foreach ($criteria as $key => $value)
		{
			$type = is_numeric($value) ? ParameterType::INTEGER : ParameterType::STRING;
			$type = is_bool($value) ? ParameterType::BOOLEAN : $type;
			$type = is_null($value) ? ParameterType::NULL : $type;

			/**
			 * This is required since $value is passed by reference in bind(). If we do not do this unholy trick the
			 * $value variable is overwritten in the next foreach() iteration, therefore all criteria values will be
			 * equal to the last value iterated. Groan...
			 */
			$varName    = 'queryParam' . ucfirst($key);
			${$varName} = $value;

			$query->where($db->qn($key) . ' = :' . $key)
				->bind(':' . $key, ${$varName}, $type);
		}

		try
		{
			$this->extensionIds[$extension] = (int) $db->setQuery($query)->loadResult();
		}
		catch (RuntimeException $e)
		{
			return null;
		}

		return $this->extensionIds[$extension];
	}

	/**
	 * Handles the package's post-flight routine
	 *
	 * @param   string               $type    Which action is happening (install|uninstall|discover_install|update)
	 * @param   PackageAdapter|null  $parent  The object responsible for running this script. NULL if running outside
	 *                                        of the package's script.
	 *
	 * @return  bool
	 */
	public function postflight(string $type, ?PackageAdapter $parent = null): bool
	{
		switch ($type)
		{
			// Brand new installation (regular or through Discover)
			case 'install':
			case 'discover_install':
				$this->runIsolated([
					'upgradeFromOldPackage',
					'uninstallExtensions',
					'publishExtensionsOnInstall',
					'removeObsoleteFiles',
					'adoptMyExtensions',
				]);

				$this->runCustomHandlerEvent('onInstall', $type, $parent);
				break;

			// Update to a new version
			case 'update':
			default:
				$this->runIsolated([
					'removeObsoleteFiles',
					'uninstallExtensions',
					'uninstallProExtensions',
					'adoptMyExtensions',
				]);

				$this->runCustomHandlerEvent('onUpdate', $type, $parent);
				break;

			// Uninstallation
			case 'uninstall':
				$this->runCustomHandlerEvent('onUninstall', $type, $parent);
				break;
		}

		return true;
	}

	/**
	 * Runs an event across all custom handler objects.
	 *
	 * @param   string  $eventName     The name of the event to run
	 * @param   mixed   ...$arguments  Arguments to the event
	 *
	 * @return  array  The results of the custom handler events.
	 */
	public function runCustomHandlerEvent(string $eventName, ...$arguments): array
	{
		$result = [];

		foreach ($this->customHandlers as $adapter)
		{
			if (!method_exists($adapter, $eventName))
			{
				continue;
			}

			try
			{
				$result[] = $adapter->{$eventName}(...$arguments);
			}
			catch (Throwable $e)
			{
				// Well, this failed. Let's move on to the next one.
			}
		}

		return $result;
	}

	/**
	 * Publish a list of extensions.
	 *
	 * Used to publish various plugins when you install the package.
	 *
	 * @return  void
	 */
	protected function publishExtensionsOnInstall(): void
	{
		$extensionIDs = array_map([$this, 'getExtensionId'], self::ENABLE_EXTENSIONS);
		$extensionIDs = array_filter($extensionIDs, function ($x) {
			return !empty($x);
		});

		if (empty($extensionIDs))
		{
			return;
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->qn('enabled') . ' = 1')
			->whereIn($db->quoteName('extension_id'), $extensionIDs);
		try
		{
			$db->setQuery($query)->execute();
		}
		catch (RuntimeException $e)
		{
			return;
		}
	}

	/**
	 * Removes obsolete files and folders.
	 *
	 * This is required because Joomla's extensions installer will only check for the top-level files and directories
	 * listed in the XML manifest. Any folders and files deeper than that will not be removed automatically.
	 *
	 * @return  void
	 * @noinspection PhpUnused
	 */
	protected function removeObsoleteFiles(): void
	{
		// We will definitely remove REMOVE_FROM_ALL_VERSIONS in all versions
		$removeSource = self::REMOVE_FROM_ALL_VERSIONS;
		$isPro        = $isPro ?? $this->isPro();

		if (!$isPro)
		{
			$removeSource['files']   = array_merge($removeSource['files'], self::REMOVE_FROM_CORE['files']);
			$removeSource['folders'] = array_merge($removeSource['folders'], self::REMOVE_FROM_CORE['folders']);
		}

		// Remove files
		foreach ($removeSource['files'] as $file)
		{
			if (!is_file($file))
			{
				continue;
			}

			File::delete($file);
		}

		// Remove folders
		foreach ($removeSource['folders'] as $folder)
		{
			if (!is_dir($folder))
			{
				continue;
			}

			Folder::delete($folder);
		}
	}

	/**
	 * Uninstalls the extensions which are marked as always to be uninstalled.
	 *
	 * @return  void
	 * @noinspection PhpUnused
	 */
	protected function uninstallExtensions(): void
	{
		// Tell Joomla to uninstall the extensions always meant to be removed.
		foreach (self::REMOVE_EXTENSIONS as $extension)
		{
			$this->uninstallExtension($extension);
		}
	}

	/**
	 * Uninstalls Pro-only extensions from the Core version of the package.
	 *
	 * @return  void
	 * @noinspection PhpUnused
	 */
	protected function uninstallProExtensions(): void
	{
		// If it's the Pro version we don't uninstall anything.
		if ($this->isPro())
		{
			return;
		}

		// Tell Joomla to uninstall the Pro-only extensions.
		foreach (self::PRO_ONLY_EXTENSIONS as $extension)
		{
			$this->uninstallExtension($extension);
		}
	}

	/**
	 * Handle the package upgrade from the old to the new package.
	 *
	 * These versions would also run on Joomla 4 but are replaced with this new package. Since the package name is
	 * different but some of the included extensions are under the same name we need to deal with them. Namely, we need
	 * to:
	 *
	 * * Change the `package_id` in the `#__extensions` table to that of the new `pkg_akeebabackup` package. This is
	 *   currently not used anywhere(?) but it might be the case that Joomla finalyl decides to prevent standalone
	 *   uninstallation of extensions which are part of a package.
	 * * Remove the extensions from the `#__akeeba_common` entries which mark them as dependent on FOF 3.x or 4.x. This
	 *   is so that FOF 3.x / 4.x can be uninstalled when the old package (`pkg_akeeba`) is being uninstalled, since
	 *   these extensions will NOT be removed with it, per the item below.
	 * * Edit the cached XML manifest file of the old `pkg_akeeba` package so that it doesn't try to uninstall the
	 *   extensions it has in common with the new `pkg_akeebabackup` package. Joomla SHOULD figure this out by means of
	 *   the recorded `package_id` in the `#__extensions` table but it currently doesn't seem to have any code to do
	 *   that. Therefore editing the cached XML manifest is the only reasonable way to do this.
	 *
	 * @return  void
	 * @noinspection PhpUnused
	 */
	protected function upgradeFromOldPackage(): void
	{
		if ($this->isSamePackage())
		{
			$this->unregisterFromFOF('3');
			$this->unregisterFromFOF('4');

			return;
		}

		if (!$this->hasOldPackage())
		{
			return;
		}

		$this->reassignExtensions();
		/** @noinspection PhpRedundantOptionalArgumentInspection */
		$this->unregisterFromFOF('3');
		$this->unregisterFromFOF('4');
		$this->removeExtensionsFromPackageManifest();
	}

	/**
	 * Convert a Joomla extension name to `#__extensions` table query criteria.
	 *
	 * The following kinds of extensions are supported:
	 * * `pkg_something` Package type extension
	 * * `com_something` Component
	 * * `plg_folder_something` Plugins
	 * * `mod_something` Site modules
	 * * `amod_something` Administrator modules. THIS IS CUSTOM.
	 * * `file_something` File type extension
	 * * `lib_something` Library type extension
	 *
	 * @param   string  $extensionName
	 *
	 * @return  string[]
	 */
	private function extensionNameToCriteria(string $extensionName): array
	{
		$parts = explode('_', $extensionName, 3);

		switch ($parts[0])
		{
			case 'pkg':
				return [
					'type'    => 'package',
					'element' => $extensionName,
				];

			case 'com':
				return [
					'type'    => 'component',
					'element' => $extensionName,
				];

			case 'plg':
				return [
					'type'    => 'plugin',
					'folder'  => $parts[1],
					'element' => $parts[2],
				];

			case 'mod':
				return [
					'type'      => 'module',
					'element'   => $extensionName,
					'client_id' => 0,
				];

			// That's how we note admin modules
			case 'amod':
				return [
					'type'      => 'module',
					'element'   => substr($extensionName, 1),
					'client_id' => 1,
				];

			case 'file':
				return [
					'type'    => 'file',
					'element' => $extensionName,
				];

			case 'lib':
				return [
					'type'    => 'library',
					'element' => $parts[1],
				];
		}

		return [];
	}

	/**
	 * Get the absolute filesystem path
	 *
	 * @param   string  $package
	 *
	 * @return  string
	 */
	private function getCachedManifestPath(string $package): string
	{
		return JPATH_MANIFESTS . '/packages/' . $package . '.xml';
	}

	/**
	 * Get the list of extensions included in a package
	 *
	 * @param   string  $package
	 *
	 * @return  array
	 */
	private function getExtensionsFromPackage(string $package): array
	{
		$extensions = [];
		$xml        = $this->getPackageXMLManifest($package);

		if (is_null($xml))
		{
			return $extensions;
		}

		foreach ($xml->xpath('//files/file') as $fileField)
		{
			$extension = $this->xmlNodeToExtensionName($fileField);

			if (is_null($extension))
			{
				continue;
			}

			$extensions[] = $extension;
		}

		return $extensions;
	}

	/**
	 * Gets a SimpleXMLElement representation of the cached manifest of the extension.
	 *
	 * @param   string  $package
	 *
	 * @return  SimpleXMLElement|null
	 */
	private function getPackageXMLManifest(string $package): ?SimpleXMLElement
	{
		$filePath = $this->getCachedManifestPath($package);

		if (!@file_exists($filePath) || !@is_readable($filePath))
		{
			return null;
		}

		$xmlContent = @file_get_contents($filePath);

		if (empty($xmlContent))
		{
			return null;
		}

		return new SimpleXMLElement($xmlContent);
	}

	/**
	 * Does the old package even exist?
	 *
	 * @return   bool
	 */
	private function hasOldPackage(): bool
	{
		if (empty(self::OLD_PACKAGE_NAME))
		{
			return false;
		}

		$eid = $this->getExtensionId(self::OLD_PACKAGE_NAME);

		return !empty($eid);
	}

	/**
	 * Is this the Pro version?
	 *
	 * This is determined by examining the constants, files and folders defined in self::PRO_CRITERIA
	 *
	 * @return  bool
	 * @see     self::PRO_CRITERIA
	 */
	private function isPro(): bool
	{
		if (empty(self::PRO_CRITERIA))
		{
			return false;
		}

		foreach (self::PRO_CRITERIA as $criterion)
		{
			[$type, $value] = $criterion;

			switch ($type)
			{
				case 'const':
				case 'constant':
					if (!defined($value))
					{
						continue 2;
					}

					if (constant($value))
					{
						return true;
					}

					break;

				case 'folder':
					if (@file_exists($value) && @is_dir($value))
					{
						return true;
					}
					break;

				case 'file':
					if (@file_exists($value) && @is_file($value))
					{
						return true;
					}
					break;

				default:
					continue 2;
			}
		}

		return false;
	}

	/**
	 * Are the old and new packages identical?
	 *
	 * Also returns true if no OLD_PACKAGE_NAME has been specified.
	 *
	 * @return  bool
	 */
	private function isSamePackage(): bool
	{
		return empty(self::OLD_PACKAGE_NAME) || (self::OLD_PACKAGE_NAME === self::PACKAGE_NAME);
	}

	/**
	 * Loads any custom handlers.
	 *
	 * @return  void
	 */
	private function loadCustomHandlers(): void
	{
		$handlerNamespace = __NAMESPACE__ . '\\' . self::CUSTOM_HANDLERS_DIRECTORY;

		$this->customHandlers = [];

		// Scan the directory and load the custom handlers
		$targetDirectory = __DIR__ . '/' . self::CUSTOM_HANDLERS_DIRECTORY;

		if (!@file_exists($targetDirectory) || !@is_dir($targetDirectory))
		{
			return;
		}

		$di = new DirectoryIterator($targetDirectory);

		/** @var DirectoryIterator $entry */
		foreach ($di as $entry)
		{
			// Ignore folders
			if ($entry->isDot() || $entry->isDir())
			{
				continue;
			}

			// Ignore non-PHP directories
			if ($entry->getExtension() != 'php')
			{
				continue;
			}

			// Get the class name
			$bareName          = basename($entry->getFilename(), '.php');
			$bareNameCanonical = preg_replace('/[^A-Z_]/i', '', $bareName);

			/**
			 * Some hosts rename files with numeric suffixes, e.g. FooBar.php is renamed to FooBar.01.php. In both cases
			 * the bare class name would be "FooBar" but the canonical would be "FooBar" vs "FooBar.01". This check
			 * makes sure that renamed files will NOT be loaded. Ever.
			 */
			if ($bareName != $bareNameCanonical)
			{
				continue;
			}

			// Have I already loaded an object this class? Yeah, sometimes hosts do weird(er) things.
			if (array_key_exists($bareNameCanonical, $this->customHandlers))
			{
				continue;
			}

			// Try to load the file
			require_once $entry->getPathname();

			// Make sure we actually loaded a class I can use
			$classFQN = $handlerNamespace . '\\' . $bareNameCanonical;

			if (!class_exists($classFQN, false))
			{
				continue;
			}

			// Add the custom handler, passing a reference to ourselves
			$this->customHandlers[$bareNameCanonical] = new $classFQN($this);
		}
	}

	/**
	 * Reassign the extensions to the new package.
	 *
	 * This modifies the package_id column of the #__extensions table for the records of the records defined in
	 * $this->extensionsList. Since these are shared between the old and new packages we need to change their package ID
	 * to the new package's ID. Otherwise Joomla might be confused as to which package "owns" them.
	 *
	 * @return  void
	 */
	private function reassignExtensions(): void
	{
		// Get the extension ID of the new package
		$newPackageId = $this->getExtensionId(self::PACKAGE_NAME);

		if (empty($newPackageId))
		{
			return;
		}

		// Get the extension IDs
		$extensionIDs = array_map([$this, 'getExtensionId'], $this->extensionsList);
		$extensionIDs = array_filter($extensionIDs, function ($x) {
			return !empty($x);
		});

		if (empty($extensionIDs))
		{
			return;
		}

		/**
		 * Looks stupid? This realigns the integer keys because whereIn() expects 0-based, monotonically increasing
		 * array keys. Otherwise it ends up emitting null values. GROAN!
		 */
		$extensionIDs = array_merge($extensionIDs);

		// Reassign all extensions
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->qn('package_id') . ' = :package_id')
			->whereIn($db->qn('extension_id'), $extensionIDs, ParameterType::INTEGER)
			->bind(':package_id', $newPackageId, ParameterType::INTEGER);
		$db->setQuery($query)->execute();
	}

	/**
	 * Removes the common extensions from old package's cached manifest.
	 *
	 * This prevents Joomla from uninstalling modules, plugins etc which are nominally included in both packages when
	 * you uninstall the old package.
	 *
	 * @return  void
	 */
	private function removeExtensionsFromPackageManifest(): void
	{
		// Make sure we have an old package and a list of extensions
		$oldPackage = self::OLD_PACKAGE_NAME;
		$extensions = $this->extensionsList;

		if (empty($oldPackage) || empty($extensions))
		{
			return;
		}

		// Get the cached manifest as a SimpleXMLElement node
		$xml = $this->getPackageXMLManifest($oldPackage);

		if (is_null($xml))
		{
			return;
		}

		// Walk through all the <file> tags and remove the extensions in the $extensions list
		foreach ($xml->xpath('//files/file') as $fileField)
		{
			$extension = $this->xmlNodeToExtensionName($fileField);

			if (is_null($extension) || !in_array($extension, $extensions))
			{
				continue;
			}

			unset($fileField[0][0]);
		}

		// Save the modified manifest back to the package manifests cache.
		$filePath = $this->getCachedManifestPath($oldPackage);
		$contents = $xml->asXML();

		File::write($filePath, $contents);
	}

	/**
	 * Runs a method inside a try/catch block to suppress any errors
	 *
	 * @param   string[]  $methodNames  The method name to run
	 *
	 * @return  void
	 */
	private function runIsolated(array $methodNames): void
	{
		foreach ($methodNames as $methodName)
		{
			try
			{
				$this->{$methodName}();
			}
			catch (Throwable $e)
			{
				// No problem, let's move on.
			}
		}
	}

	/**
	 * Uninstall an extension by name.
	 *
	 * @param   string  $extension
	 *
	 * @return  bool
	 */
	private function uninstallExtension(string $extension): bool
	{
		// Let's get the extension ID. If it's not there we can't uninstall this extension, right..?
		$eid = $this->getExtensionId($extension);

		if (empty($eid))
		{
			return false;
		}

		// Get an Extension table object and Installer object.
		/** @noinspection PhpParamsInspection */
		$row       = new Extension($this->getDbo());
		$installer = Installer::getInstance();

		// Load the extension row or fail the uninstallation immediately.
		try
		{
			if (!$row->load($eid))
			{
				return false;
			}
		}
		catch (Throwable $e)
		{
			// If the database query fails or Joomla experiences an unplanned rapid deconstruction let's bail out.
			return false;
		}

		// Can't uninstalled protected extensions
		/** @noinspection PhpUndefinedFieldInspection */
		if ((int) $row->locked === 1)
		{
			return false;
		}

		// An extension row without a type? What have you done to your database, you MONSTER?!
		if (empty($row->type))
		{
			return false;
		}

		// Do the actual uninstallation. Try to trap any errors, just in case...
		try
		{
			return $installer->uninstall($row->type, $eid);
		}
		catch (Throwable $e)
		{
			return false;
		}
	}

	/**
	 * Unregisters a list of extensions from being marked as dependent on the specified FOF version.
	 *
	 * @param   string  $fofVersion  PHP version to unregister the extensions from
	 *
	 * @return  void
	 */
	private function unregisterFromFOF($fofVersion = '3')
	{
		// Make sure we have an extensions list and it's canonical (admin modules have mod_ prefix, not amod_).
		$extensions = $this->extensionsList;
		$extensions = array_map(function ($name) {
			if (substr($name, 0, 5) == 'amod_')
			{
				$name = 'mod_' . substr($name, 5);
			}

			return $name;
		}, $extensions);

		// Get the existing list of extensions dependent on the specified version of FOF.
		$keyName = 'fof' . $fofVersion . '0';
		$db      = $this->getDbo();
		$query   = $db->getQuery(true)
			->select($db->quoteName('value'))
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = :keyName')
			->bind(':keyName', $keyName);
		try
		{
			$json = $db->setQuery($query)->loadResult();
			$list = json_decode($json, true);
		}
		catch (RuntimeException $e)
		{
			return;
		}

		// If the list is empty I am already done.
		if (is_null($list) || !is_array($list))
		{
			return;
		}

		// Remove the common extensions which no longer depend on FOF.
		$list = array_diff($list, $extensions);
		$json = json_encode($list);

		// Update the #__akeeba_common table.
		$query = $db->getQuery(true)
			->update($db->quoteName('#__akeeba_common'))
			->set($db->quoteName('value') . ' = :json')
			->where($db->quoteName('key') . ' = :keyName')
			->bind(':json', $json)
			->bind(':keyName', $keyName);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (RuntimeException $e)
		{
			return;
		}
	}

	/**
	 * Take a SimpleXMLElement `<file>` node of the package manifest and return the corresponding Joomla extension name
	 *
	 * @param   SimpleXMLElement  $fileField  The `<file>` node of the package manifest
	 *
	 * @return  string|null  The extension name, null if it cannot be determined.
	 */
	private function xmlNodeToExtensionName(SimpleXMLElement $fileField): ?string
	{
		$type = (string) $fileField->attributes()->type;
		$id   = (string) $fileField->attributes()->id;

		switch ($type)
		{
			case 'component':
			case 'file':
			case 'library':
				$extension = $id;
				break;

			case 'plugin':
				$group     = (string) $fileField->attributes()->group ?? 'system';
				$extension = 'plg_' . $group . '_' . $id;
				break;

			case 'module':
				$client    = (string) $fileField->attributes()->client ?? 'site';
				$extension = (($client != 'site') ? 'a' : '') . $id;
				break;

			default:
				$extension = null;
				break;
		}

		return $extension;
	}
}