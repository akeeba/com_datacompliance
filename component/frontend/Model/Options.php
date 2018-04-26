<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\Model;

defined('_JEXEC') or die;

use FOF30\Model\DataModel\Exception\RecordNotLoaded;
use FOF30\Model\Model;
use FOF30\Utils\Ip;
use JHtml;

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
			\JModelLegacy::addIncludePath(JPATH_BASE . '/components/com_content/models', 'ContentModel');
			/** @var \ContentModelArticle $contentModel */
			$contentModel = \JModelLegacy::getInstance('Article', 'ContentModel');
			$article = $contentModel->getItem($articleId);
		}
		catch (\Exception $e)
		{
			return '';
		}

		return
			JHtml::_('content.prepare', $article->introtext) .
			JHtml::_('content.prepare', $article->fulltext);
	}

	/**
	 * Get the consent preference of a user
	 *
	 * @param   \JUser|null  $user  The user to get the status for, or null for the current user.
	 *
	 * @return  bool
	 */
	public function getPreference(\JUser $user = null)
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		/** @var Consenttrails $consent */
		$consent = $this->container->factory->model('Consenttrails')->tmpInstance();

		try
		{
			return (bool)($consent->findOrFail(['created_by' => $user->id])->enabled);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Record the user preference (or update their preference)
	 *
	 * @param   bool  $preference  Their data protection preference
	 *
	 * @throws  \Exception
	 */
	public function recordPreference($preference = false, \JUser $user = null)
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
	}

	/**
	 * Get the human readable list of actions to be taken when deleting a user account
	 *
	 * @param   \JUser|null  $user  The user account we will be deleting
	 * @param   string       $type  The deletion method (user, admin, lifecycle)
	 *
	 * @return  array  An array of strings representing the actions (bullet points) to show to the user
	 */
	public function getBulletPoints(\JUser $user = null, string $type = 'user')
	{
		if (is_null($user))
		{
			$user = $this->container->platform->getUser();
		}

		$this->container->platform->importPlugin('datacompliance');
		$results = $this->container->platform->runPlugins('onDataComplianceGetWipeBulletpoints', [$user->id, $type]);

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
}