<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\TemplateEmails;
use Akeeba\Component\DataCompliance\Administrator\Model\UpgradeModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Adapter\PackageAdapter;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;

/**
 * Akeeba Data Compliance package extension installation script file.
 *
 * @see https://docs.joomla.org/Manifest_files#Script_file
 * @see UpgradeModel
 */
class Pkg_DatacomplianceInstallerScript extends InstallerScript
{
	/**
	 * @since 3.1.0
	 * @var   DatabaseDriver|DatabaseInterface|null
	 */
	protected $dbo;

	public function __construct()
	{
		$this->minimumJoomla = '4.2.0';
		$this->minimumPhp    = '7.4.0';
	}

	/**
	 * Called after any type of installation / uninstallation action.
	 *
	 * @param   string          $type    Which action is happening (install|uninstall|discover_install|update)
	 * @param   PackageAdapter  $parent  The object responsible for running this script
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function postflight(string $type, PackageAdapter $parent): bool
	{
		$this->setDboFromAdapter($parent);

		// Do not run on uninstall.
		if ($type === 'uninstall')
		{
			return true;
		}

		$model = $this->getUpgradeModel();

		if (empty($model))
		{
			return true;
		}

		if (!empty($model))
		{
			try
			{
				if (!$model->postflight($type, $parent))
				{
					return false;
				}
			}
			catch (Exception $e)
			{
				return false;
			}
		}

		$this->updateEmails();

		return true;
	}

	/**
	 * Get the UpgradeModel of the installed component
	 *
	 * @return  UpgradeModel|null  The upgrade Model. NULL if it cannot be loaded.
	 * @since   1.0.0
	 */
	private function getUpgradeModel(): ?UpgradeModel
	{
		// Make sure the latest version of the Model file will be loaded, regardless of the OPcache state.
		$filePath = JPATH_ADMINISTRATOR . '/components/com_datacompliance/src/Model/UpgradeModel.php';

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($filePath = JPATH_ADMINISTRATOR . '/components/com_datacompliance/src/Model/UpgradeModel.php', true);
		}

		// Can I please load the model?
		if (!class_exists('\Akeeba\Component\DataCompliance\Administrator\Model\UpgradeModel'))
		{
			if (!file_exists($filePath) || !is_readable($filePath))
			{
				return null;
			}

			include_once $filePath;
		}

		if (!class_exists('\Akeeba\Component\DataCompliance\Administrator\Model\UpgradeModel'))
		{
			return null;
		}

		try
		{
			$upgradeModel = new UpgradeModel();
		}
		catch (Throwable $e)
		{
			return null;
		}

		if (method_exists($upgradeModel, 'setDatabase'))
		{
			$upgradeModel->setDatabase($this->dbo ?? Factory::getContainer()->get(DatabaseInterface::class));
		}
		elseif (method_exists($upgradeModel, 'setDbo'))
		{
			$upgradeModel->setDbo($this->dbo ?? Factory::getContainer()->get(DatabaseInterface::class));
		}

		if (method_exists($upgradeModel, 'init'))
		{
			$upgradeModel->init();
		}

		return $upgradeModel;
	}

	private function updateEmails(): void
	{
		// Make sure the latest version of the Helper file will be loaded, regardless of the OPcache state.
		$filePath = JPATH_ADMINISTRATOR . '/components/com_datacompliance/src/Helper/TemplateEmails.php';

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($filePath, true);
		}

		if (!class_exists('\Akeeba\Component\DataCompliance\Administrator\Helper\TemplateEmails'))
		{
			if (!file_exists($filePath) || !is_readable($filePath))
			{
				return;
			}

			/** @noinspection PhpIncludeInspection */
			include_once $filePath;
		}

		if (!class_exists('\Akeeba\Component\DataCompliance\Administrator\Helper\TemplateEmails'))
		{
			return;
		}

		try
		{
			TemplateEmails::updateAllTemplates();
		}
		catch (Exception $e)
		{
		}
	}

	/**
	 * Set the database object from the installation adapter, if possible
	 *
	 * @param   InstallerAdapter|mixed  $adapter  The installation adapter, hopefully.
	 *
	 * @return  void
	 * @since   3.1.0
	 */
	private function setDboFromAdapter($adapter): void
	{
		$this->dbo = null;

		if (class_exists(InstallerAdapter::class) && ($adapter instanceof InstallerAdapter))
		{
			/**
			 * If this is Joomla 4.2+ the adapter has a protected getDatabase() method which we can access with the
			 * magic property $adapter->db. On Joomla 4.1 and lower this is not available. So, we have to first figure
			 * out if we can actually use the magic property...
			 */

			try
			{
				$refObj = new ReflectionObject($adapter);

				if ($refObj->hasMethod('getDatabase'))
				{
					$this->dbo = $adapter->db;

					return;
				}
			}
			catch (Throwable $e)
			{
				// If something breaks we will fall through
			}
		}

		$this->dbo = Factory::getContainer()->get(DatabaseInterface::class);
	}
}
