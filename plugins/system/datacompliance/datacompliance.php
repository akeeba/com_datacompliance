<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use FOF30\Container\Container;

// Prevent direct access
defined('_JEXEC') or die;

// Minimum PHP version check
if (!version_compare(PHP_VERSION, '7.0.0', '>='))
{
	return;
}

// Make sure Akeeba DataCompliance is installed
if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_datacompliance'))
{
	return;
}

// Load FOF
if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
{
	return;
}

/**
 * Akeeba DataCompliance System Plugin
 *
 * Implements the captive login page for Data Processing Consent
 */
class PlgSystemDatacompliance extends JPlugin
{
	/**
	 * Are we enabled, all requirements met etc?
	 *
	 * @var   bool
	 *
	 * @since   1.0.0
	 */
	public $enabled = true;

	/**
	 * The component's container
	 *
	 * @var   Container
	 *
	 * @since   1.0.0
	 */
	private $container = null;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   1.0.0
	 */
	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);

		try
		{
			if (!JComponentHelper::isInstalled('com_datacompliance') || !JComponentHelper::isEnabled('com_datacompliance'))
			{
				$this->enabled = false;
			}
			else
			{
				$this->container = Container::getInstance('com_datacompliance');
			}
		}
		catch (Exception $e)
		{
			$this->enabled = false;
		}
	}

	/**
	 * Gets triggered right after Joomla has finished with the SEF routing and before it has the chance to dispatch the
	 * application (load any components).
	 *
	 * @return  void
	 *
	 * @throws Exception
	 *
	 * @since   1.0.0
	 */
	public function onAfterRoute()
	{
		// If the requirements are not met do not proceed
		if (!$this->enabled)
		{
			return;
		}

		// Get the session objects
		try
		{
			$session = JFactory::getSession();
		}
		catch (Exception $e)
		{
			// Can't get access to the session? Must be under CLI which is not supported.
			return;
		}

		// We only kick in if the session flag is not set (saves a lot of processing time)
		if ($session->get('has_consented', 0, 'com_datacompliance'))
		{
			return;
		}

		// Make sure we are logged in
		try
		{
			$app = JFactory::getApplication();

			// Joomla! 3: make sure the user identity is loaded. This MUST NOT be called in Joomla! 4, though.
			if (version_compare(JVERSION, '3.99999.99999', 'lt'))
			{
				$app->loadIdentity();
			}

			$user = $app->getIdentity();
		}
		catch (\Exception $e)
		{
			// This would happen if we are in CLI or under an old Joomla! version. Either case is not supported.
			return;
		}

		// The plugin only needs to kick in when you have logged in
		if ($user->get('guest'))
		{
			return;
		}

		$isBackend = $this->container->platform->isBackend();
		$isCli     = $this->container->platform->isCli();

		// This is not applicable under CLI
		if ($isCli)
		{
			return;
		}

		// If we are in the administrator section we only kick in when the user has backend access privileges
		if ($isBackend && !$user->authorise('core.login.admin'))
		{
			return;
		}

		// We only kick in if the option and task are not the ones of the captive page
		$option = strtolower($app->input->getCmd('option'));
		$task   = strtolower($app->input->getCmd('task'));
		$view   = strtolower($app->input->getCmd('view'));

		// DO NOT kick in if we are in an exempt component / view / task
		if ($this->isExempt($option, $task, $view))
		{
			return;
		}

		if ($option == 'com_datacompliance')
		{
			// In case someone gets any funny ideas...
			$app->input->set('tmpl', 'index');
			$app->input->set('format', 'html');
			$app->input->set('layout', null);

			// Note the check for an empty view. That's because Options is the default view of the component.
			$allowedViews = array('options', 'Options', 'option', 'Option', '');

			if ($isBackend)
			{
				// But in the backend the default view is ControlPanel so we can't allow an empty view. Sorry :)
				$allowedViews = array_slice($allowedViews, 0, count($allowedViews) - 1);
			}

			if (in_array($view, $allowedViews))
			{
				return;
			}
		}

		// Allow the frontend user to log out
		if (!$isBackend && ($option == 'com_users') && ($task == 'user.logout'))
		{
			return;
		}

		// Allow the backend user to log out
		if ($isBackend && ($option == 'com_login') && ($task == 'logout'))
		{
			return;
		}

		// We only kick in when the user has not consented already
		$needsConsent = $this->needsConsent($user);

		if ($needsConsent && $this->hasAkeebasubsConsent($user))
		{
			$needsConsent = false;
		}

		if ($needsConsent)
		{
			// Save the current URL, but only if we haven't saved a URL or if the saved URL is NOT internal to the site.
			$return_url = $session->get('return_url', '', 'com_datacompliance');

			if (empty($return_url) || !JUri::isInternal($return_url))
			{
				$session->set('return_url', JUri::getInstance()->toString(array(
					'scheme',
					'user',
					'pass',
					'host',
					'port',
					'path',
					'query',
					'fragment',
				)), 'com_datacompliance');
			}

			// Redirect
			$this->loadLanguage();
			$message = JText::_('PLG_SYSTEM_DATACOMPLIANCE_MSG_MUSTACCEPT');
			$app->enqueueMessage($message, 'warning');
			$url = JRoute::_('index.php?option=com_datacompliance&view=Options', false);
			$app->redirect($url, 307);

			return;
		}

		// If we're here someone just logged in but has already consented
		$session->set('has_consented', 1, 'com_datacompliance');
	}

	/**
	 * Does the current user need to provide consent for processing their personal information?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	private function needsConsent(JUser $user)
	{
		/** @var \Akeeba\DataCompliance\Admin\Model\Consenttrails $consentModel */
		$consentModel = $this->container->factory->model('Consenttrails')->tmpInstance();

		try
		{
			$consentModel->findOrFail(['created_by' => $user->id]);
		}
		catch (\FOF30\Model\DataModel\Exception\RecordNotLoaded $e)
		{
			// No consent record. The user must provide their consent preference.
			return true;
		}

		// If the user has not consented then yes, they must provide consent.
		return $consentModel->enabled != 1;
	}

	/**
	 * Is the current option / view / task combination exempt from redirection? We use this to allow other captive
	 * logins, such as LoginGuard, to work with Data Consent without causing an infinite redirection loop
	 *
	 * @param   string  $option  The current component
	 * @param   string  $view    The current view
	 * @param   string  $task    The current task
	 *
	 * @return  bool
	 *
     * @since   1.0.0
	 */
	private function isExempt($option, $view, $task)
	{
		$rawConfig = $this->params->get('exempt', '');
		$rawConfig = trim($rawConfig);

		if (empty($rawConfig))
		{
			return false;
		}

		$rawConfig = str_replace("\r\n", ',', $rawConfig);
		$rawConfig = str_replace("\n", ',', $rawConfig);
		$rawConfig = str_replace("\r", ',', $rawConfig);

		$configList = explode(',', $rawConfig);

		foreach ($configList as $configItem)
		{
			// Explode the item up to three levels deep (option.view.task)
			$explodedItem = explode('.', $configItem, 3);

			// Only an option was provided
			if (count($explodedItem) == 1)
			{
				$explodedItem[] = '*';
				$explodedItem[] = '*';
			}

			// Only an option and view was provided
			if (count($explodedItem) == 2)
			{
				$explodedItem[] = '*';
			}


			list ($checkOption, $checkView, $checkTask) = $explodedItem;

			// If the option is not '*' and does not match the current one this is not a match; move on
			if (($checkOption != '*') && (strtolower($checkOption) != strtolower($option)))
			{
				continue;
			}

			// If the view is not '*' and does not match the current one this is not a match; move on
			if (($checkView != '*') && (strtolower($checkView) != strtolower($view)))
			{
				continue;
			}

			// If the task is not '*' and does not match the current one this is not a match; move on
			if (($checkTask != '*') && (strtolower($checkTask) != strtolower($task)))
			{
				continue;
			}

			// We have a match! We can return early.
			return true;
		}

		// No match found.
		return false;
	}

	/**
	 * Do I have consent recorded by Akeeba Subscriptions? This returns true only when there is no Data Compliance
	 * consent record and there is an Akeeba Subs consent recorded. In this case we transcribe the consent into Data
	 * Compliance. If the user has withdrawn his consent through Data Compliance this method returns false.
	 *
	 * @param  JUser  $user  The user to check
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	private function hasAkeebasubsConsent(JUser $user)
	{
		// Is Akeeba Subs installed and enabled?
		if (!JComponentHelper::isInstalled('com_akeebasubs') || !JComponentHelper::isEnabled('com_akeebasubs'))
		{
			return false;
		}

		// Only transcribe if there is no consent record yet
		/** @var \Akeeba\DataCompliance\Admin\Model\Consenttrails $consentModel */
		$consentModel = $this->container->factory->model('Consenttrails')->tmpInstance();

		try
		{
			$consentModel->findOrFail(['created_by' => $user->id]);

			return false;
		}
		catch (\FOF30\Model\DataModel\Exception\RecordNotLoaded $e)
		{
			// No consent record. AWESOME! That's what I need!
		}

		// Try to fetch the Akeeba Subs user record
		$asContainer = Container::getInstance('com_akeebasubs');
		/** @var \Akeeba\Subscriptions\Site\Model\Users $asUser */
		$asUser = $asContainer->factory->model('Users')->tmpInstance();

		try
		{
			$asUser->findOrFail(['user_id' => $user->id]);
		}
		catch (\FOF30\Model\DataModel\Exception\RecordNotLoaded $e)
		{
			// No record found. Bye bye.
			return false;
		}

		// Get the user parameters and see if they have consented to EU data.
		$params = $asUser->params;

		if (!is_object($params) || !($params instanceof \JRegistry))
		{
			JLoader::import('joomla.registry.registry');
			$params = new \JRegistry($params);
		}

		$confirmEUData = $this->isTruthism($params->get('confirm_eudata', false));

		if (!$confirmEUData)
		{
			// Not consented (this means they subscribed before we ran the consent)
			return false;
		}

		// They have consented. Record their consent as the date and IP of their latest subscription.
		/** @var \Akeeba\Subscriptions\Site\Model\Subscriptions $subModel */
		$subModel = $asContainer->factory->model('Subscriptions')->tmpInstance();
		$jNow     = $asContainer->platform->getDate();
		$jWhen    = $asContainer->platform->getDate('2010-01-01 00:00:00');
		$ip       = '';
		$allSubs  = $subModel->user_id($user->id)->paystate(['C'])->until($jNow->toSql())->get();

		if (empty($allSubs))
		{
			// What? No subscriptions? They can't have possibly consented then :/
			return false;
		}

		/** @var \Akeeba\Subscriptions\Site\Model\Subscriptions $sub */
		foreach ($allSubs as $sub)
		{
			// Only take into account a newer subscription
			$jThisSub = $asContainer->platform->getDate($sub->created_on);

			if ($jThisSub->toUnix() <= $jWhen->toUnix())
			{
				continue;
			}

			// Do I have an IP address?
			if (empty($sub->ip))
			{
				continue;
			}

			// Yup, found a subscription
			$ip    = $sub->ip;
			$jWhen = $jThisSub;
		}

		if (empty($ip))
		{
			return false;
		}

		// Transcribe the consent record from Akeeba Subscriptions to Data Compliance
		$consentModel->create([
			'enabled'      => 1,
		]);

		$consentModel
			->findOrFail(['created_by' => $user->id])
			->save([
				'created_on'   => $jWhen->toSql(),
				'requester_ip' => $ip,
			]);

		return true;
	}

	/**
	 * Checks if a value set in Akeeba Subs is a truthism.
	 *
	 * @param   string  $value  The value to check
	 *
	 * @return  bool  THe value as a boolean
	 *
	 * @since   1.0.0
	 */
	private function isTruthism($value)
	{
		if ($value === 1) return true;

		if (in_array($value, ['on', 'checked', 'true', '1', 'yes', 1, true], true))
		{
			return true;
		}

		return false;
	}
}
