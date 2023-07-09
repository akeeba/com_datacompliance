<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\DataCompliance\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Table\ConsenttrailsTable;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

class DataCompliance extends CMSPlugin implements SubscriberInterface
{
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;

	/**
	 * Constructor
	 *
	 * @param   DispatcherInterface  &    $subject     The object to observe
	 * @param   array                     $config      An optional associative array of configuration settings.
	 *                                                 Recognized key values include 'name', 'group', 'params',
	 *                                                 'language' (this list is not meant to be comprehensive).
	 * @param   MVCFactoryInterface|null  $mvcFactory  The MVC factory for the Data Compliance component.
	 *
	 * @since   3.0.0
	 */
	public function __construct(&$subject, $config = [], MVCFactoryInterface $mvcFactory = null)
	{
		if (!empty($mvcFactory))
		{
			$this->setMVCFactory($mvcFactory);
		}

		parent::__construct($subject, $config);
	}

	/**
	 * Return the mapping of event names and public methods in this object which handle them
	 *
	 * @return string[]
	 * @since  3.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		if (!ComponentHelper::isEnabled('com_datacompliance'))
		{
			return [];
		}

		return [
			'onAfterRoute' => 'onAfterRoute',
		];
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
	public function onAfterRoute(Event $event)
	{
		$session = $this->getApplication()->getSession();

		// We only kick in if the session flag is not set (saves a lot of processing time)
		if ($session->get('com_datacompliance.has_consented', 0))
		{
			return;
		}

		// Make sure we are logged in
		try
		{
			$user = $this->getApplication()->getIdentity();
		}
		catch (Exception $e)
		{
			return;
		}

		// The plugin only needs to kick in when you have logged in
		if ($user->get('guest'))
		{
			return;
		}

		$isBackend = $this->getApplication()->isClient('administrator');

		// This is not applicable under CLI
		if ($this->getApplication()->isClient('cli'))
		{
			return;
		}

		// If we are in the administrator section we only kick in when the user has backend access privileges
		if ($isBackend && !$user->authorise('core.login.admin'))
		{
			return;
		}

		// We only kick in if the option and task are not the ones of the captive page
		$input  = $this->getApplication()->input;
		$option = strtolower($input->getCmd('option') ?: '');
		$task   = strtolower($input->getCmd('task') ?: '');
		$view   = strtolower($input->getCmd('view', $input->getCmd('controller', '')) ?: '');

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
			$input->set('tmpl', 'index');
			$input->set('format', 'html');
			$input->set('layout', null);

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

		/**
		 * If Joomla's privacy consent plugin is enabled and we are still here it means that the user has not provided
		 * consent with Joomla just yet. Joomla Privacy Consent will hijack the redirect. If we try to redirect as well
		 * we will end up in an infinite redirection loop. Therefore we will just quit here and let Joomla handle the
		 * consent in its subpar way.
		 *
		 * Smart users will read the fine manual and disable Joomla's stupid plugin instead, using the superior Akeeba
		 * Data Compliance consent experience instead.
		 */
		if (PluginHelper::isEnabled('system', 'privacyconsent'))
		{
			return;
		}

		if ($needsConsent)
		{
			// Save the current URL, but only if we haven't saved a URL or if the saved URL is NOT internal to the site.
			$return_url = $session->get('com_datacompliance.return_url', '');

			if (empty($return_url) || !Uri::isInternal($return_url))
			{
				$session->set('com_datacompliance.return_url', Uri::getInstance()->toString([
					'scheme',
					'user',
					'pass',
					'host',
					'port',
					'path',
					'query',
					'fragment',
				]));
			}

			// Redirect
			$this->loadLanguage();
			$message = Text::_('PLG_SYSTEM_DATACOMPLIANCE_MSG_MUSTACCEPT');
			$this->getApplication()->enqueueMessage($message, 'warning');
			$url = Route::_('index.php?option=com_datacompliance&view=options', false);
			$this->getApplication()->redirect($url, 307);

			return;
		}

		// If we're here someone just logged in but has already consented
		$session->set('com_datacompliance.has_consented', 1);
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
	 * @throws  Exception
	 * @since   1.1.1
	 */
	private function hasJoomlaConsent(User $user): bool
	{
		// Get the consent information from Joomla
		$db     = $this->getDatabase();
		$query  = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__privacy_consents'))
			->where($db->quoteName('user_id') . ' = :userid')
			->where($db->quoteName('subject') . ' = ' . $db->quote('PLG_SYSTEM_PRIVACYCONSENT_SUBJECT'))
			->where($db->quoteName('state') . ' = 1')
			->bind(':userid', $user->id, ParameterType::INTEGER);

		$result = $db->setQuery($query)->loadResult() ?: null;

		if (is_null($result) || ((int)$result <= 0))
		{
			// They have not consented
			return false;
		}

		/**
		 * They have consented. Since Joomla! does not collect consent date and IP information as machine readable data
		 * we will record the current date and IP address. The transcription only takes place is there is no Data
		 * Compliance consent record yet.
		 */
		/** @var ConsenttrailsTable $consentTable */
		$consentTable = $this->getMVCFactory()->createTable('Consenttrails', 'Administrator');

		if ($consentTable->load($user->id))
		{
			return true;
		}

		$consentTable->save([
			'created_on' => (clone Factory::getDate())->toSql(),
			'created_by' => $user->id,
			'enabled' => 1
		]);

		return true;
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
		if ($this->getApplication()->getIdentity()->get('requireReset', 0))
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
	 * Does the current user need to provide consent for processing their personal information?
	 *
	 * @param   User  $user  The user to check
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function needsConsent(User $user): bool
	{
		/** @var ConsenttrailsTable $consentTable */
		$consentTable = $this->getMVCFactory()->createTable('Consenttrails', 'Administrator');

		if (!$consentTable->load($user->id))
		{
			// No consent record. The user must provide their consent preference.
			return true;
		}

		// If the user has not consented then yes, they must provide consent.
		return $consentTable->enabled != 1;
	}
}