<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
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
 * Akeeba DataCompliance User Plugin
 *
 * Allows the user to access the Data Options page from within the User Profile page
 */
class PlgUserDatacompliance extends JPlugin
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
	 * Adds additional fields to the user editing form
	 *
	 * @param   JForm $form The form to be altered.
	 * @param   mixed $data The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 *
	 * @throws Exception
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!($form instanceof JForm))
		{
			throw new InvalidArgumentException('JERROR_NOT_A_FORM');
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();

		if (!in_array($name, array('com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration')))
		{
			return true;
		}

		$layout = JFactory::getApplication()->input->getCmd('layout', 'default');

		if (!$this->container->platform->isBackend() && !in_array($layout, array('edit', 'default')))
		{
			return true;
		}

		// Get the user ID
		$id = null;

		if (is_array($data))
		{
			$id = isset($data['id']) ? $data['id'] : null;
		}
		elseif (is_object($data) && is_null($data) && ($data instanceof JRegistry))
		{
			$id = $data->get('id');
		}
		elseif (is_object($data) && !is_null($data))
		{
			$id = isset($data->id) ? $data->id : null;
		}

		$user = JFactory::getUser($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			return true;
		}

		// Make sure I am either editing myself (you can NOT make choices on behalf of another user).
		if (!$this->canEditUser($user))
		{
			return true;
		}

		// Add the fields to the form.
		JForm::addFormPath(dirname(__FILE__) . '/datacompliance');

		// At this point we should load our language files.
		$this->loadLanguage();

		// Special handling for profile overview page
		if ($layout == 'default')
		{
			/** @var \Akeeba\DataCompliance\Site\Model\Consenttrails $consentModel */
			$consentModel = $this->container->factory->model('Consenttrails')->tmpInstance();

			try
			{
				$consentModel->findOrFail(['created_by' => $id]);
				$hasConsent = $consentModel->enabled ? 1 : 0;
			}
			catch (\FOF30\Model\DataModel\Exception\RecordNotLoaded $e)
			{
				$hasConsent = 0;
			}

			/**
			 * We cannot pass a boolean or integer; if it's false/0 Joomla! will display "No information entered". We
			 * cannot use a list field to display it in a human readable format, Joomla! just dumps the raw value if you
			 * use such a field. So all I can do is pass raw text. Um, whatever.
			 */
			$data->loginguard = array(
				'hastfa' => $hasConsent ? JText::_('JYES') : JText::_('JNO')
			);

			$form->loadFile('list', false);

			return true;
		}

		// Profile edit page
		$form->loadFile('datacompliance', false);

		return true;
	}

	/**
	 * Is the current user allowed to edit the TFA configuration of $user? To do so I must be editing my own account.
	 * Since the content must be explicit under GDPR nobody can do it on your behalf.
	 *
	 * @param   JUser|\Joomla\CMS\User\User  $user  The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	private function canEditUser($user = null)
	{
		// I can edit myself
		if (empty($user))
		{
			return true;
		}

		// Guests can't have TFA
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in used
		$myUser = $this->container->platform->getUser();

		// Same user? I can edit myself
		if ($myUser->id == $user->id)
		{
			return true;
		}

		// Whatever. I am not the same person. Go away.
		return true;
	}

}
