<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use FOF30\Container\Container;
use Joomla\CMS\Application\CMSApplication;
use plgSystemDataComplianceCookieHelper as CookieHelper;

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
 * Akeeba DataCompliance Cookie Conformance System Plugin
 *
 * Removes cookies unless explicitly allowed
 */
class PlgSystemDatacompliancecookie extends JPlugin
{
	/**
	 * Are we enabled, all requirements met etc?
	 *
	 * @var    bool
	 *
	 * @since  1.1.0
	 */
	public $enabled = true;

	/**
	 * The component's container
	 *
	 * @var    Container
	 *
	 * @since  1.1.0
	 */
	private $container = null;

	/**
	 * Has the user accepted cookies from this site?
	 *
	 * @var    bool
	 *
	 * @since  1.1.0
	 */
	private $hasAcceptedCookies = false;

	/**
	 * Has the user recorded his preference regarding cookies?
	 *
	 * @var    bool
	 *
	 * @since  1.1.0
	 */
	private $hasCookiePreference = false;

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

		// Self-disable on admin pages or when we cannot get a reference to the CMS application (e.g. CLI app).
		try
		{
			if (JFactory::getApplication()->isClient('administrator'))
			{
				throw new RuntimeException("This plugin should not load on administrator pages.");
			}
		}
		catch (Exception $e)
		{
			// This code block also catches the case where JFactory::getApplication() crashes, e.g. CLI applications.
			$this->enabled = false;

			return;
		}

		// Self-disable if our component is not enabled.
		try
		{
			if (!JComponentHelper::isInstalled('com_datacompliance') || !JComponentHelper::isEnabled('com_datacompliance'))
			{
				throw new RuntimeException('Component not installed');
			}

			$this->container = Container::getInstance('com_datacompliance');
		}
		catch (Exception $e)
		{
			$this->enabled = false;

			return;
		}

		// Load the helper class. Self-disable if it's not available.
		if (!class_exists('plgSystemDataComplianceHelper'))
		{
			include_once __DIR__ . '/helper/helper.php';
		}

		if (!class_exists('plgSystemDataComplianceCookieHelper'))
		{
			$this->enabled = false;

			return;
		}

		// Get some options
		$cookieName        = $this->params->get('cookieName', 'plg_system_datacompliancecookie');
		$impliedAcceptance = $this->params->get('impliedAccept', 0) != 0;

		// Set up the name of the user preference (helper) cookie we are going to use in this plugin
		CookieHelper::setCookieName($cookieName);

		// Get the user's cookie acceptance preferences
		$this->hasAcceptedCookies  = CookieHelper::hasAcceptedCookies($impliedAcceptance);
		$this->hasCookiePreference = CookieHelper::getDecodedCookieValue() !== false;
	}

	public function onAjaxDatacompliancecookie()
	{
		// Am I already disabled...?
		if (!$this->enabled)
		{
			return;
		}

		// TODO Create an AJAX handler for the cookie preference.

		// TODO I will need to call CookieHelper::setAcceptedCookies to record the user's preference
	}

	/**
	 * Runs early in the application startup, right after Joomla has done basic preparation and loaded the system
	 * plugins.
	 *
	 * @return  void
	 *
	 * @see     \Joomla\CMS\Application\CMSApplication::initialiseApp()
	 */
	public function onAfterInitialise()
	{
		// Am I already disabled...?
		if (!$this->enabled)
		{
			return;
		}

		if (!$this->hasAcceptedCookies)
		{
			// Remove all cookies
			$this->removeAllCookies();

			// TODO Add the user to the "No cookies" user group
		}
		else
		{
			// TODO Add the user to the "Accepted cookies" user group
		}

	}

	/**
	 * Called after Joomla! has routed the application (figured out SEF redirections and is about to load the component)
	 *
	 * @return  void
	 *
	 * @see     \Joomla\CMS\Application\CMSApplication::route()
	 */
	public function onAfterRoute()
	{
		// Am I already disabled...?
		if (!$this->enabled)
		{
			return;
		}

		// If the format is not 'html' or the tmpl is not one of the allowed values we should not run.
		try
		{
			$app = JFactory::getApplication();

			if ($app->input->getCmd('format', 'html') != 'html')
			{
				throw new RuntimeException("This plugin should not run in non-HTML application formats.");
			}

			if (!in_array($app->input->getCmd('tmpl', ''), ['', 'index', 'component'], true))
			{
				throw new RuntimeException("This plugin should not run for application templates which do not predictably result in HTML output.");
			}
		}
		catch (Exception $e)
		{
			$this->enabled = false;

			return;
		}

		$jsOptions = [];

		if (!$this->hasAcceptedCookies)
		{
			// Remove all cookies before the component is loaded
			$this->removeAllCookies();

			// TODO Load the JavaScript to show the cookie consent modal
		}
		else
		{
			// TODO Load the JavaScript to show the manage cookie options controls
		}

		$this->loadCommonJavascript($app, $jsOptions);
	}

	/**
	 * Called after Joomla! has rendered the document and before it is sent to the browser.
	 *
	 * @return  void
	 *
	 * @see     \Joomla\CMS\Application\CMSApplication::execute()
	 */
	public function onAfterRender()
	{
		// Am I already disabled...?
		if (!$this->enabled)
		{
			return;
		}

		// TODO Is our JavaScript in the output? If yes, return.

		if (!$this->hasAcceptedCookies)
		{
			// Remove any cookies which may have been set by the component and modules
			$this->removeAllCookies();

			// TODO Load the JavaScript to show the cookie consent modal
		}
		else
		{
			// TODO Load the JavaScript to show the manage cookie options controls
		}
	}

	/**
	 * Remove all cookies which are already set or about to be set
	 *
	 * @return  void
	 */
	private function removeAllCookies()
	{
		$allowSessionCookie      = $this->params->get('allowSessionCookie', 1) !== 0;
		$additionalCookieDomains = trim($this->params->get('additionalCookieDomains', ''));

		if (!empty($additionalCookieDomains))
		{
			$additionalCookieDomains = array_map(function ($x) {
				return trim($x);
			}, explode("\n", $additionalCookieDomains));
		}

		$additionalCookieDomains = is_array($additionalCookieDomains) ? $additionalCookieDomains : [];

		CookieHelper::unsetAllCookies($allowSessionCookie, $additionalCookieDomains);
	}

	/**
	 * Load the common Javascript for this plugin
	 *
	 * @param   CMSApplication  $app      The CMS application we are interfacing
	 * @param   array           $options  Additional options to pass to the JavaScript
	 */
	private function loadCommonJavascript($app, array $options = [])
	{
		$path   = $app->get('cookie_path', '/');
		$domain = $app->get('cookie_domain', filter_input(INPUT_SERVER, 'HTTP_HOST'));
		$js     = <<< JS
; //
var AkeebaDataComplianceCookiesOptions = {
	cookie: {
		domain: '$domain',
		path:   '$path',
	}
};
JS;

		$this->container->template->addJSInline($js);
		$this->container->template->addJS('media://plg_system_datacompliancecookie/js/datacompliancecookies.js', true, true, $this->container->mediaVersion);

		// TODO Add language strings which should be made known to JS
	}
}
