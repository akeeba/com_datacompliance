<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Model\Consenttrails;
use FOF40\Container\Container;
use FOF40\Model\DataModel\Exception\RecordNotLoaded;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

// Prevent direct access
defined('_JEXEC') or die;

// Minimum PHP version check
if (!version_compare(PHP_VERSION, '7.2.0', '>='))
{
	return;
}

// Make sure Akeeba DataCompliance is installed
if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_datacompliance'))
{
	return;
}

// Load FOF
if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
{
	return;
}

/**
 * Akeeba DataCompliance System Plugin
 *
 * Implements the captive login page for Data Processing Consent
 */
class PlgSystemDatacompliance extends CMSPlugin
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
	 * @param   array    $config   An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   1.0.0
	 */
	public function __construct($subject, array $config = [])
	{
		parent::__construct($subject, $config);

		try
		{
			if (!ComponentHelper::isInstalled('com_datacompliance') || !ComponentHelper::isEnabled('com_datacompliance'))
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
	 * @throws  Exception
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

		// We only kick in if the session flag is not set (saves a lot of processing time)
		if ($this->container->platform->getSessionVar('has_consented', 0, 'com_datacompliance'))
		{
			return;
		}

		// Make sure we are logged in
		try
		{
			$app = Factory::getApplication();

			// Joomla! 3: make sure the user identity is loaded. This MUST NOT be called in Joomla! 4, though.
			if (version_compare(JVERSION, '3.99999.99999', 'lt'))
			{
				$app->loadIdentity();
			}

			$user = $app->getIdentity();
		}
		catch (Exception $e)
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
		$fallbackView = version_compare(JVERSION, '3.999.999', 'ge')
			? $app->input->getCmd('controller', '')
			: '';
		$option       = strtolower($app->input->getCmd('option'));
		$task         = strtolower($app->input->getCmd('task'));
		$view         = strtolower($app->input->getCmd('view', $fallbackView));

		if (strpos($task, '.') !== false)
		{
			$parts = explode('.', $task);
			$view  = ($parts[0] ?? $view) ?: $view;
			$task  = ($parts[1] ?? $task) ?: $task;
		}

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
			$allowedViews = ['options', 'Options', 'option', 'Option', ''];

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

		if ($needsConsent && $this->hasJoomlaConsent($user))
		{
			$needsConsent = false;
		}

		if ($needsConsent)
		{
			// Save the current URL, but only if we haven't saved a URL or if the saved URL is NOT internal to the site.
			$return_url = $this->container->platform->getSessionVar('return_url', '', 'com_datacompliance');

			if (empty($return_url) || !Uri::isInternal($return_url))
			{
				$this->container->platform->setSessionVar('return_url', Uri::getInstance()->toString([
					'scheme',
					'user',
					'pass',
					'host',
					'port',
					'path',
					'query',
					'fragment',
				]), 'com_datacompliance');
			}

			// Redirect
			$this->snuffJoomlaPrivacyConsent();
			$this->loadLanguage();
			$message = Text::_('PLG_SYSTEM_DATACOMPLIANCE_MSG_MUSTACCEPT');
			$app->enqueueMessage($message, 'warning');
			$url = Route::_('index.php?option=com_datacompliance&view=Options', false);
			$app->redirect($url, 307);

			return;
		}

		// If we're here someone just logged in but has already consented
		$this->container->platform->setSessionVar('has_consented', 1, 'com_datacompliance');
	}

	/**
	 * Does the current user need to provide consent for processing their personal information?
	 *
	 * @param   User  $user  The user to check
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	private function needsConsent(User $user): bool
	{
		/** @var Consenttrails $consentModel */
		$consentModel = $this->container->factory->model('Consenttrails')->tmpInstance();

		try
		{
			$consentModel->findOrFail(['created_by' => $user->id]);
		}
		catch (RecordNotLoaded $e)
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
	private function isExempt($option, $view, $task): bool
	{
		// If Joomla requires a password reset we should not try to redirect or it'll cause an infinite redirection loop
		if (Factory::getUser()->get('requireReset', 0))
		{
			return true;
		}

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


			[$checkOption, $checkView, $checkTask] = $explodedItem;

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
	 * Do I have consent recorded by Joomla's com_privacy component? This returns true only when there is no Data
	 * Compliance consent record and there is a Joomla! com_privacy consent recorded in the user's profile. In this case
	 * we transcribe the consent into Data Compliance. If the user has withdrawn his consent through Data Compliance
	 * this method returns false.
	 *
	 * Only applies to Joomla! 3.9.0 or later
	 *
	 * @param   User  $user  The user to check
	 *
	 * @return  bool
	 *
	 * @since   1.1.1
	 */
	private function hasJoomlaConsent(User $user): bool
	{
		// Is this Joomla! 3.9.0 or later?
		if (version_compare(JVERSION, '3.9.0', 'lt'))
		{
			return false;
		}

		// Only transcribe if there is no consent record yet
		/** @var Consenttrails $consentModel */
		$consentModel = $this->container->factory->model('Consenttrails')->tmpInstance();

		try
		{
			$consentModel->findOrFail(['created_by' => $user->id]);

			return false;
		}
		catch (RecordNotLoaded $e)
		{
			// No consent record. AWESOME! That's what I need!
		}

		// Get the consent information from Joomla
		$db     = $this->container->db;
		$query  = $db->getQuery(true)
			->select($db->qn('profile_value'))
			->from($db->qn('#__user_profiles'))
			->where($db->quoteName('user_id') . ' = ' . (int) ($user->id))
			->where($db->quoteName('profile_key') . ' = ' . $db->q('privacyconsent.privacy'));
		$result = $db->setQuery($query)->loadResult();

		if (is_null($result) && ($result != 1))
		{
			// They have not consented
			return false;
		}

		/**
		 * They have consented. Since Joomla! does not collect consent date and IP information as machine readable data
		 * we will record the current date and IP address.
		 */
		$consentModel->create([
			'enabled' => 1,
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
	private function isTruthism($value): bool
	{
		if ($value === 1)
		{
			return true;
		}

		if (in_array($value, ['on', 'checked', 'true', '1', 'yes', 1, true], true))
		{
			return true;
		}

		return false;
	}

	/**
	 * Kills the Joomla Privacy Consent plugin when we are showing the Two Step Verification.
	 *
	 * JPC uses captive login code copied from our DataCompliance component. However, they removed the exceptions we
	 * have for other captive logins. As a result the JPC captive login interfered with LoginGuard's captive login,
	 * causing an infinite redirection.
	 *
	 * Due to complete lack of support for exceptions, this method here does something evil. It hunts down the observer
	 * (plugin hook) installed by the JPC plugin and removes it from the loaded plugins. This prevents the redirection
	 * of the captive login. THIS IS NOT THE BEST WAY TO DO THINGS. You should NOT ever, EVER!!!! copy this code. I am
	 * someone who has spent 15+ years dealing with Joomla's core code and I know what I'm doing, why I'm doing it and,
	 * most importantly, how it can possibly break. don't go about merrily copying this code if you do not understand
	 * how Joomla event dispatching works. You'll break shit and I'm not to blame. Thank you!
	 *
	 * @throws ReflectionException
	 * @since  3.0.4
	 */
	private function snuffJoomlaPrivacyConsent()
	{
		/**
		 * The privacy suite is not ported to Joomla! 4 yet.
		 */
		if (version_compare(JVERSION, '3.9999.9999', 'ge'))
		{
			return;
		}

		// The broken Joomla! consent plugin is not activated
		if (!class_exists('PlgSystemPrivacyconsent'))
		{
			return;
		}

		// Get the events dispatcher and find which observer is the offending plugin
		$dispatcher    = JEventDispatcher::getInstance();
		$refDispatcher = new ReflectionObject($dispatcher);
		$refObservers  = $refDispatcher->getProperty('_observers');
		$refObservers->setAccessible(true);
		$observers = $refObservers->getValue($dispatcher);

		$jConsentObserverId = 0;

		foreach ($observers as $id => $o)
		{
			if (!is_object($o))
			{
				continue;
			}

			if ($o instanceof \PlgSystemPrivacyconsent)
			{
				$jConsentObserverId = $id;

				break;
			}
		}

		// Nope. Cannot find the offending plugin.
		if ($jConsentObserverId == 0)
		{
			return;
		}

		// Now we need to remove the offending plugin from the onAfterRoute event.
		$refMethods = $refDispatcher->getProperty('_methods');
		$refMethods->setAccessible(true);
		$methods = $refMethods->getValue($dispatcher);

		$methods['onafterroute'] = array_filter($methods['onafterroute'], function ($id) use ($jConsentObserverId) {
			return $id != $jConsentObserverId;
		});
		$refMethods->setValue($dispatcher, $methods);
	}

}
