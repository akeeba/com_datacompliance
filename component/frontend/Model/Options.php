<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\Model;

use Exception;
use FOF40\Model\DataModel\Exception\RecordNotLoaded;
use FOF40\Model\Model;
use FOF40\IP\IPHelper as Ip;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\User;

defined('_JEXEC') or die;

class Options extends Model
{
	/**
	 * Returns the text of the article containing the Privacy and Cookies Policy in simple and clear language per the
	 * GDPR.
	 *
	 * Note: only the intro and full text are returned. They are always both returned, it does not respect the don't
	 * show intro text option of the article. The article must be visible by the current user.
	 *
	 * @return  string  The article's HTML content
	 */
	public function getArticle(): string
	{
		$articleId = $this->container->params->get('policyarticle', 0);

		// Do I have an article to show?
		if ($articleId <= 0)
		{
			return '';
		}

		// Try to load the article
		try
		{
			jimport('joomla.model.model');
			BaseDatabaseModel::addIncludePath(JPATH_BASE . '/components/com_content/models', 'ContentModel');
			/** @var \ContentModelArticle $contentModel */
			$contentModel = BaseDatabaseModel::getInstance('Article', 'ContentModel');
			$article      = $contentModel->getItem($articleId);
		}
		catch (Exception $e)
		{
			return '';
		}

		return
			HTMLHelper::_('content.prepare', $article->introtext) .
			HTMLHelper::_('content.prepare', $article->fulltext);
	}

	/**
	 * Get the consent preference of a user
	 *
	 * @param   User|null  $user  The user to get the status for, or null for the current user.
	 *
	 * @return  bool
	 */
	public function getPreference(User $user = null)
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		/** @var Consenttrails $consent */
		$consent = $this->container->factory->model('Consenttrails')->tmpInstance();

		try
		{
			return (bool) ($consent->findOrFail(['created_by' => $user->id])->enabled);
		}
		catch (RecordNotLoaded $e)
		{
			return false;
		}
	}

	/**
	 * Record the user preference (or update their preference)
	 *
	 * @param   bool  $preference  Their data protection preference
	 *
	 * @throws  Exception
	 */
	public function recordPreference($preference = false, User $user = null)
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		/** @var Consenttrails $consent */
		$consent = $this->container->factory->model('Consenttrails')->tmpInstance();

		try
		{
			$consent->findOrFail(['created_by' => $user->id])->bind([
				'enabled'      => $preference,
				'requester_ip' => Ip::getIp(),
			])->save();
		}
		catch (RecordNotLoaded $e)
		{
			$consent->create(['enabled' => $preference]);
		}

		/**
		 * Finally, unset the session flag used by the system plugin.
		 *
		 * This means that if you go from Consent to Non-consent the plugin will evaluate your option again and redirect
		 * you to the consent page, preventing you from using the site (as it should).
		 */
		$this->container->platform->setSessionVar('has_consented', 0, 'com_datacompliance');
	}

	/**
	 * Get the human readable list of actions to be taken when deleting a user account
	 *
	 * @param   User|null  $user  The user account we will be deleting
	 * @param   string     $type  The deletion method (user, admin, lifecycle)
	 *
	 * @return  array  An array of strings representing the actions (bullet points) to show to the user
	 *
	 * @throws  Exception
	 */
	public function getBulletPoints(?User $user = null, string $type = 'user')
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		$this->importPlugin('datacompliance');
		$results = $this->runPlugins('onDataComplianceGetWipeBulletpoints', [$user->id, $type]);

		$ret = [];

		foreach ($results as $result)
		{
			if (empty($result) || !is_array($result))
			{
				continue;
			}

			$ret = array_merge($ret, $result);
		}

		return $ret;
	}

	/**
	 * Load plugins of a specific type.
	 *
	 * This is a simple shim to FOF, ensuring that plugins WILL be loaded under CLI.
	 *
	 * @param   string  $type  The type of the plugins to be loaded
	 *
	 * @return  void
	 */
	public function importPlugin($type)
	{
		if ($this->container->platform->isCli())
		{
			$this->container->platform->setAllowPluginsInCli(true);
		}

		$this->container->platform->importPlugin($type);
	}

	/**
	 * Execute plugins (system-level triggers) and fetch back an array with their return values. Do not go through FOF;
	 * it does not run that under CLI
	 *
	 * @param   string  $event  The event (trigger) name, e.g. onBeforeScratchMyEar
	 * @param   array   $data   A hash array of data sent to the plugins as part of the trigger
	 *
	 * @return  array  A simple array containing the results of the plugins triggered
	 *
	 * @throws  Exception
	 */
	public function runPlugins($event, $data)
	{
		if (class_exists('JEventDispatcher'))
		{
			return \JEventDispatcher::getInstance()->trigger($event, $data);
		}

		return \JFactory::getApplication()->triggerEvent($event, $data);
	}
}