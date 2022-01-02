<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Model;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Table\ConsenttrailsTable;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\Utilities\IpHelper;

class OptionsModel extends BaseDatabaseModel
{
	/**
	 * Returns the text of the article containing the Privacy and Cookies Policy in simple and clear language per the
	 * GDPR.
	 *
	 * Note: only the intro and full text are returned. They are always both returned, it does not respect the don't
	 * show intro text option of the article. The article must be visible by the current user.
	 *
	 * @return  string  The article's HTML content
	 * @since   1.0.0
	 */
	public function getArticle(): string
	{
		$cParams   = ComponentHelper::getParams('com_datacompliance');
		$articleId = $cParams->get('policyarticle', 0);

		// Do I have an article to show?
		if ($articleId <= 0)
		{
			return '';
		}

		// Try to load the article
		try
		{
			/** @var MVCFactoryInterface $factory */
			$mvcFactory = Factory::getApplication()->bootComponent('com_content')->getMVCFactory();;
			/** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $contentModel */
			$contentModel = $mvcFactory->createModel('Article', 'Administrator');
			$article      = $contentModel->getItem($articleId);
		}
		catch (Exception $e)
		{
			return '';
		}

		if (!isset($article) || empty($article))
		{
			return '';
		}

		return
			HTMLHelper::_('content.prepare', $article->introtext) .
			HTMLHelper::_('content.prepare', $article->fulltext);
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
	 * @since   1.0.0
	 */
	public function getBulletPoints(?User $user = null, string $type = 'user'): array
	{
		if (is_null($user))
		{
			$user = Factory::getApplication()->getIdentity();
		}

		PluginHelper::importPlugin('datacompliance');
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
	 * Get the consent preference of a user
	 *
	 * @param   User|null  $user  The user to get the status for, or null for the current user.
	 *
	 * @return  bool
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function getPreference(User $user = null): bool
	{
		if (is_null($user))
		{
			$user = Factory::getApplication()->getIdentity();
		}

		/** @var ConsenttrailsTable $consent */
		$consent = $this->getMVCFactory()->createTable('Consenttrails', 'Administrator');

		if (!$consent->load($user->id))
		{
			return false;
		}

		return $consent->enabled == 1;
	}

	/**
	 * Record the user preference (or update their preference)
	 *
	 * @param   bool  $preference  Their data protection preference
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function recordPreference(bool $preference = false, User $user = null): void
	{
		/** @var CMSApplication $app */
		$app = Factory::getApplication();

		if (is_null($user))
		{
			$user = $app->getIdentity();
		}

		/** @var ConsenttrailsTable $consent */
		$consent = $this->getMVCFactory()->createTable('Consenttrails', 'Administrator');

		if (!$consent->load($user->id))
		{
			$consent->reset();
			$consent->created_by = $user->id;
			$consent->created_on = (new Date())->toSql();
		}

		$consent->requester_ip = IpHelper::getIp();
		$consent->enabled      = $preference ? 1 : 0;
		$consent->store(true);

		/**
		 * Finally, unset the session flag used by the system plugin.
		 *
		 * This means that if you go from Consent to Non-consent the plugin will evaluate your option again and redirect
		 * you to the consent page, preventing you from using the site (as it should).
		 */
		$app->getSession()->set('com_datacompliance.has_consented', $preference ? 1 : 0);
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
	 * @since   1.0.0
	 */
	public function runPlugins(string $event, array $data): array
	{
		return Factory::getApplication()->triggerEvent($event, $data);
	}
}