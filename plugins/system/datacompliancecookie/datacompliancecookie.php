<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use FOF30\Container\Container;
use FOF30\Utils\DynamicGroups;
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
	 * Have I already included the JavaScript in the HTML page?
	 *
	 * @var    bool
	 *
	 * @since  1.1.0
	 */
	private $haveIncludedJavaScript = false;

	/**
	 * Have I already included the CSS in the HTML page?
	 *
	 * @var    bool
	 *
	 * @since  1.1.0
	 */
	private $haveIncludedCSS = false;

	/**
	 * Have I already included the HTML for the cookie banner or controls in the HTML page?
	 *
	 * @var    bool
	 *
	 * @since  1.1.0
	 */
	private $haveIncludedHtml = false;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   1.1.0
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

	/**
	 * Handler for AJAX interactions with the plugin
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
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
	 *
	 *
	 * @since   1.1.0
	 */
	public function onAfterInitialise()
	{
		// Am I already disabled...?
		if (!$this->enabled)
		{
			return;
		}

		// Note that permanent user group assignment IS NOT possible for guest (not logged in) users
		$user                     = $this->container->platform->getUser();
		$permanentGroupAssignment = ($this->params->get('permanentUserGroupAssignment', 0) == 1) && !$user->guest;
		$rejectGroup              = $this->params->get('cookiesRejectedUserGroup', 0);
		$acceptGroup              = $this->params->get('cookiesEnabledUserGroup', 0);

		// Do I have to do permanent user group assignment
		if ($permanentGroupAssignment && !$user->guest)
		{
			// TODO Permanent group assignment depending on $this->hasAcceptedCookies

		}

		if (!$this->hasAcceptedCookies)
		{
			// Remove all cookies
			$this->removeAllCookies();

			/**
			 * Add the user to the selected "No cookies" user group.
			 *
			 * IMPORTANT! This must happen EVEN IF permanent assignment is requested since Joomla! does NOT reload the
			 * user group assignments until you log back in.
			 */
			if ($rejectGroup != 0)
			{
				DynamicGroups::addGroup($rejectGroup);
			}

			return;
		}

		/**
		 * Add the user to the selected "Accepted cookies" user group.
		 *
		 * IMPORTANT! This must happen EVEN IF permanent assignment is requested since Joomla! does NOT reload the
		 * user group assignments until you log back in.
		 */
		if ($acceptGroup != 0)
		{
			DynamicGroups::addGroup($acceptGroup);
		}
	}

	/**
	 * Called after Joomla! has routed the application (figured out SEF redirections and is about to load the component)
	 *
	 * @return  void
	 *
	 * @see     \Joomla\CMS\Application\CMSApplication::route()
	 *
	 * @since   1.1.0
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

		if (!$this->hasAcceptedCookies)
		{
			// Remove all cookies before the component is loaded
			$this->removeAllCookies();
		}

		$this->loadCommonJavascript($app);
		$this->loadCommonCSS($app);

		// Note: we cannot load the HTML yet. This can only be done AFTER the document is rendered.
	}

	/**
	 * Called after Joomla! has rendered the document and before it is sent to the browser.
	 *
	 * @return  void
	 *
	 * @see     \Joomla\CMS\Application\CMSApplication::execute()
	 *
	 * @since   1.1.0
	 */
	public function onAfterRender()
	{
		// Am I already disabled...?
		if (!$this->enabled)
		{
			return;
		}

		if (!$this->hasAcceptedCookies)
		{
			// Remove any cookies which may have been set by the component and modules
			$this->removeAllCookies();
		}

		try
		{
			// Load the common JavaScript
			$app = JFactory::getApplication();
			$this->loadCommonJavascript($app);
			$this->loadCommonCSS($app);

			$this->loadHtml($app);
		}
		catch (Exception $e)
		{
			// Sorry, we cannot get a Joomla! application :(
		}
	}

	/**
	 * Remove all cookies which are already set or about to be set
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	private function removeAllCookies()
	{
		$allowSessionCookie      = $this->params->get('allowSessionCookie', 1) !== 0;
		$additionalCookieDomains = $this->getAdditionalCookieDomains();

		CookieHelper::unsetAllCookies($allowSessionCookie, $additionalCookieDomains);
	}

	/**
	 * Get the additional cookie domains as an array
	 *
	 * @return array
	 *
	 * @since  1.1.0
	 */
	private function getAdditionalCookieDomains(): array
	{
		$additionalCookieDomains = trim($this->params->get('additionalCookieDomains', ''));

		if (!empty($additionalCookieDomains))
		{
			$additionalCookieDomains = array_map(function ($x) {
				return trim($x);
			}, explode("\n", $additionalCookieDomains));
		}

		$additionalCookieDomains = is_array($additionalCookieDomains) ? $additionalCookieDomains : [];

		if (empty($additionalCookieDomains))
		{
			$additionalCookieDomains = CookieHelper::getDefaultCookieDomainNames();
		}

		return $additionalCookieDomains;
	}

	/**
	 * Load the common Javascript for this plugin
	 *
	 * @param   CMSApplication  $app      The CMS application we are interfacing
	 * @param   array           $options  Additional options to pass to the JavaScript (overrides defaults)
	 *
	 * @since   1.1.0
	 */
	private function loadCommonJavascript($app, array $options = [])
	{
		// Prevent double inclusion of the JavaScript
		if ($this->haveIncludedJavaScript)
		{
			return;
		}

		$this->haveIncludedJavaScript = true;

		// Get the default options for the cookie killer JavaScript
		$path   = $app->get('cookie_path', '/');
		$domain = $app->get('cookie_domain', filter_input(INPUT_SERVER, 'HTTP_HOST'));

		$whiteList          = [CookieHelper::getCookieName()];
		$allowSessionCookie = $this->params->get('allowSessionCookie', 1) !== 0;

		// If the session cookie is allowed I need to whitelist it too.
		if ($allowSessionCookie)
		{
			$whiteList[] = CookieHelper::getSessionCookieName();
			$whiteList[] = 'joomla_user_state';
		}

		$defaultOptions = [
			'accepted'                => $this->hasAcceptedCookies,
			'interacted'              => $this->hasCookiePreference,
			'cookie'                  => [
				'domain' => $domain,
				'path'   => $path,
			],
			'additionalCookieDomains' => $this->getAdditionalCookieDomains(),
			'whitelisted'             => $whiteList,
		];

		$options     = array_merge_recursive($defaultOptions, $options);
		$optionsJSON = json_encode($options, JSON_PRETTY_PRINT);

		$js = <<< JS
; //
var AkeebaDataComplianceCookiesOptions = $optionsJSON;

JS;

		$this->container->template->addJSInline($js);
		$this->container->template->addJS('media://plg_system_datacompliancecookie/js/datacompliancecookies.js', true, false, $this->container->mediaVersion);

		// TODO Add language strings which should be made known to JS
	}

	/**
	 * Load the common CSS for this plugin
	 *
	 * @param   CMSApplication  $app      The CMS application we are interfacing
	 * @param   array           $options  Additional options to pass to the JavaScript (overrides defaults)
	 *
	 * @since   1.1.0
	 */
	private function loadCommonCSS($app, array $options = [])
	{
		// Prevent double inclusion of the CSS
		if ($this->haveIncludedCSS)
		{
			return;
		}

		$this->haveIncludedCSS = true;

		// FEF
		$useFEF   = $this->params->get('load_fef', 1);
		$useReset = $this->params->get('fef_reset', 1);

		if ($useFEF)
		{
			$helperFile = JPATH_SITE . '/media/fef/fef.php';

			if (!class_exists('AkeebaFEFHelper') && is_file($helperFile))
			{
				include_once $helperFile;
			}

			if (class_exists('AkeebaFEFHelper'))
			{
				\AkeebaFEFHelper::load($useReset);
			}
		}

		// Plugin CSS
		$this->container->template->addCSS('media://plg_system_datacompliancecookie/css/datacompliancecookies.css', $this->container->mediaVersion);
	}

	/**
	 * Load the HTML template used by our JavaScript for either the cookie acceptance banner or the post-acceptance
	 * cookie controls (revoke consent or reconsider declining cookies).
	 *
	 * @param   JApplicationCms $app The CMS application we use to append the HTML output
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	private function loadHtml($app)
	{
		// Prevent double inclusion of the HTML
		if ($this->haveIncludedHtml)
		{
			return;
		}

		$this->haveIncludedHtml = true;

		$this->loadLanguage();

		// Get the correct view template, depending on whether we have accepted cookies
		$template = 'plugin://system/datacompliancecookie/banner.php';

		if ($this->hasAcceptedCookies)
		{
			$template = 'plugin://system/datacompliancecookie/controls.php';
		}

		$fileName = $this->container->template->parsePath($template, true);

		ob_start();
		include $fileName;
		$content = ob_get_clean();

		// Append the parsed view template content to the application's HTML output
		$app->setBody($app->getBody() . $content);
	}
}
