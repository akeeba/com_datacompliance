<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Helper;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\DbQuery;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class ComponentParams
{
	/**
	 * Actually Save the params into the db
	 *
	 * @param   Registry  $params
	 *
	 * @since   9.0.0
	 */
	public static function save(Registry $params): void
	{
		/** @var DatabaseDriver $db */
		$db   = JoomlaFactory::getContainer()->get(DatabaseInterface::class);
		$data    = $params->toString('JSON');
		$element = 'com_datacompliance';
		$type    = 'component';

		$sql = DbQuery::create($db)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = :params')
			->where($db->qn('element') . ' = :element')
			->where($db->qn('type') . ' = :type')
			->bind(':params', $data, ParameterType::STRING)
			->bind(':element', $element, ParameterType::STRING)
			->bind(':type', $type, ParameterType::STRING);

		$db->setQuery($sql);

		try
		{
			$db->execute();

			// The component parameters are cached. We just changed them. Therefore we MUST reset the system cache which holds them.
			CacheCleaner::clearCacheGroups(['_system'], [0, 1]);
		}
		catch (\Exception $e)
		{
			// Don't sweat if it fails
		}

		// Reset ComponentHelper's cache
		$refClass = new \ReflectionClass(ComponentHelper::class);
		$refProp  = $refClass->getProperty('components');

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$components = $refClass->getStaticPropertyValue('components');
		}
		else
		{
			$components = $refProp->getValue();
		}

		$components['com_datacompliance']->params = $params;

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$refClass->setStaticPropertyValue('components', $components);
		}
		else
		{
			$refProp->setValue($components);
		}

	}
}