<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Site\Model\Cookietrails;
use FOF30\Container\Container;
use FOF30\Utils\DynamicGroups;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use plgSystemDataComplianceCookieHelper as CookieHelper;

// Prevent direct access
defined('_JEXEC') or die;

/**
 * Akeeba DataCompliance Cookie Conformance System Plugin
 *
 * Removes cookies unless explicitly allowed
 */
class PlgSystemDatacompliancecookie extends JPlugin
{
	/**
	 * @var   \Joomla\CMS\Application\SiteApplication
	 */
	public $app;

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
	 * The value of the Do Not Track (DNT) header sent by the browser. -1 = not set, 0 = allow tracking, 1 = do not
	 * track.
	 *
	 * @var    int
	 * @since  1.1.0
	 */
	private $dnt = -1;

	/**
	 * Am I currently handling an AJAX request? This is populated in onAfterInitialise and it's used to prevent other
	 * event handlers from firing when we are processing an AJAX request.
	 *
	 * @var    bool
	 *
	 * @since  1.1.0
	 */
	private $inAjax = false;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   1.1.0
	 */
	public function __construct($subject, array $config = [])
	{
		parent::__construct($subject, $config);

		// Self-disable if the Akeeba DataCompliance component is not installed or disabled
		if (!ComponentHelper::isEnabled('com_datacompliance'))
		{
			$this->enabled = false;

			return;
		}

		// Self-disable if FOF cannot be loaded
		if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
		{
			$this->enabled = false;

			return;
		}

		$app = $this->app;

		// Self-disable in off-line mode
		if ($app->get('offline') == 1)
		{
			$this->enabled = false;

			return;
		}

		// Self-disable if we're not on the public site.
		if (!method_exists($app, 'isClient') || !$app->isClient('site'))
		{
			$this->enabled = false;

			return;
		}

		// Self-disable if we can't get the component's container
		try
		{
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
		$dntCompliance     = $this->params->get('dntCompliance', 'ignore');
		$useDNTforImplied  = $dntCompliance != 'ignore';

		// Set up the name of the user preference (helper) cookie we are going to use in this plugin
		CookieHelper::setCookieName($cookieName);

		// Get the DNT header value
		$this->dnt = $this->getDNTValue();

		if ($useDNTforImplied && ($this->dnt !== -1))
		{
			$impliedAcceptance = $this->dnt === 0;
		}

		// Get the user's cookie acceptance preferences
		$this->hasAcceptedCookies  = CookieHelper::hasAcceptedCookies($impliedAcceptance);
		$this->hasCookiePreference = CookieHelper::getDecodedCookieValue() !== false;

		/**
		 * The DNT header is set, the user's option cookie is NOT set and I was told to treat the DNT header as the
		 * user's concrete preference.
		 */
		if (($this->dnt !== -1) && ($dntCompliance == 'overridepreference') && (CookieHelper::getDecodedCookieValue() === false))
		{
			$thisManyDays = $this->params->get('cookiePreferenceDuaration', 90);
			$accepted     = $this->dnt === 0;
			CookieHelper::setAcceptedCookies($accepted, $thisManyDays);
			$this->logAuditTrail($accepted, $this->dnt, false);
		}
	}

	/**
	 * Handler for AJAX interactions with the plugin
	 *
	 * @return  string|Throwable  The message to send back to the application, or an Exception in case of an error
	 *
	 * @since   1.1.0
	 */
	public function onAjaxDatacompliancecookie()
	{
		// Am I already disabled...?
		if (!$this->enabled)
		{
			return new RuntimeException('Cookie conformance is not applicable', 101);
		}

		// Prevent other event handlers in the plugin from firing
		$this->enabled = false;

		$token    = $this->container->platform->getToken();
		$hasToken = $this->container->input->post->get($token, false, 'none') == 1;

		if (!$hasToken)
		{
			return new RuntimeException('Invalid security token; this request is a forgery and has not been taken into account.', 102);
		}

		$accepted = $this->container->input->post->getInt('accepted', null);
		$reset    = $this->container->input->post->getInt('reset', null);

		if (is_null($accepted) && is_null($reset))
		{
			return new RuntimeException('No cookie preference was provided and no cookie preference reset was requested.', 103);
		}

		if ($reset)
		{
			// Reset the cookie preference. Cookie acceptance is set to the implied acceptance value.
			$accepted         = $this->params->get('impliedAccept', 0) != 0;
			$useDNTforImplied = $this->params->get('dntCompliance', 'ignore') != 'ignore';

			if ($useDNTforImplied && ($this->dnt !== -1))
			{
				$accepted = $this->dnt === 0;
			}

			CookieHelper::removeCookiePreference($accepted);
			$this->logAuditTrail($accepted, $useDNTforImplied ? $this->dnt : -2, true);

			$ret = sprintf("The cookie preference has been cleared. Cookies are now %s per default setting.", $accepted ? 'accepted' : 'rejected');
		}
		else
		{
			// Set the cookie preference to the user's setting.
			$thisManyDays = $this->params->get('cookiePreferenceDuaration', 90);
			CookieHelper::setAcceptedCookies($accepted === 1, $thisManyDays);
			$this->logAuditTrail($accepted, -2, false);

			$ret = sprintf("The user has %s cookies", $accepted ? 'accepted' : 'rejected');
		}

		// Apply the user group assignments based on the cookie preference
		$this->applyUserGroupAssignments();

		// Remove all cookies if the user has rejected cookies
		if (!$accepted)
		{
			$this->removeAllCookies();
		}

		return $ret;
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

		/**
		 * When we are in com_ajax we should defer execution of this code until after we have handled the request.
		 * Otherwise the I Agree is never honored if the default cookie acceptance state is "declined".
		 */
		$input  = $this->container->input->get;
		$option = $input->getCmd('option', '');
		$group  = $input->getCmd('group', '');
		$plugin = $input->getCmd('plugin', '');

		if (($group == 'system') && ($plugin == 'datacompliancecookie'))
		{
			$this->inAjax = true;

			return;
		}

		// Apply the user group assignments based on the cookie preference
		$this->applyUserGroupAssignments();

		// Remove all cookies if the user has rejected cookies
		if (!$this->hasAcceptedCookies)
		{
			$this->removeAllCookies();
		}

		/**
		 * Only for the frontend.
		 *
		 * If I am inside a page of Data Compliance or LoginGuard I should not show the cookie acceptance banner.
		 * These components are special cases. The former requires the user to provide their consent to their
		 * personal information being processed, the latter is two step verification. If I block their interface
		 * they block the cookie banner functionality, therefore the user cannot do anything!
		 */
		if (!$this->container->platform->isFrontend())
		{
			// I am not in the frontend. Nothing to do.
			return;
		}

		if ($this->hasCookiePreference)
		{
			// The user has already provided a preference, the banner won't be shown anyway.
			return;
		}

		if ($option == 'com_datacompliance')
		{
			// The user is trying to give / revoke their consent or export / delete their profile.
			$this->enabled = false;

			return;
		}

		if ($option == 'com_loginguard')
		{
			// The user is trying to undergo two step verification.
			$this->enabled = false;

			return;
		}
	}

	/**
	 * Called after Joomla! has routed the application (figured out SEF redirections and is about to load the component)
	 *
	 * WARNING! DO NOT ANY CSS / JS LOADING HERE. Joomla 4 HAS NOT INITIALISED THE DOCUMENT YET.
	 *
	 * @return  void
	 *
	 * @see     \Joomla\CMS\Application\CMSApplication::route()
	 *
	 * @since   1.1.0
	 */
	public function onAfterRoute()
	{
		// Am I already disabled or in AJAX handling mode?
		if (!$this->enabled || $this->inAjax)
		{
			return;
		}

		$app = $this->app;

		// If the format is not 'html' or the tmpl is not one of the allowed values we should not run.
		try
		{
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

		// Note: we cannot load the HTML yet. This can only be done AFTER the document is rendered.
	}

	/**
	 * Called after Joomla has finished processing the main component.
	 *
	 * We MUST NOT combine this with onAfterRoute because Joomla 4 initializes the document late.
	 */
	public function onAfterDispatch()
	{
		// Am I already disabled or in AJAX handling mode?
		if (!$this->enabled || $this->inAjax)
		{
			return;
		}

		$app = $this->app;

		// If the format is not 'html' or the tmpl is not one of the allowed values we should not run.
		try
		{
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

		// Load FEF if necessary
		$useFEF = $this->params->get('load_fef', 1);

		if ($useFEF)
		{
			if (!class_exists('AkeebaFEFHelper'))
			{
				@include_once JPATH_ROOT . '/media/fef/fef.php';
			}

			$useReset = $this->params->get('fef_reset', 1);
			$darkMode = $this->params->get('dark_mode', -1) != 0;

			if (class_exists('AkeebaFEFHelper'))
			{
				AkeebaFEFHelper::load($useReset, $darkMode);
			}
		}

		// Pass some useful URLs to the frontend
		$this->container->platform->addScriptOptions('com_datacompliance.applyURL',
			Route::_('index.php?option=com_ajax&group=system&plugin=datacompliancecookie&format=json', false));
		$this->container->platform->addScriptOptions('com_datacompliance.removeURL',
			Route::_('index.php?option=com_ajax&group=system&plugin=datacompliancecookie&format=json', false));
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
		// Am I already disabled or in AJAX handling mode?
		if (!$this->enabled || $this->inAjax)
		{
			return;
		}

		if (!$this->hasAcceptedCookies)
		{
			// Remove any cookies which may have been set by the component and modules
			$this->removeAllCookies();
		}

//		if ($this->enabled && !$this->inAjax)
//		{
//			$this->loadCommonJavascript();
//			$this->loadCommonCSS();
//		}

		$app = $this->app;

		// Load the common JavaScript
		$additionalContent = '';
		$additionalContent .= $this->loadCommonJavascript($app);
		$additionalContent .= $this->loadCommonCSS($app);

		// Load the HTML content for the banner and cookie status notifications
		$this->loadHtml($app, $additionalContent);
	}

	/**
	 * Get the DNT preference
	 *
	 * @return  int
	 * @since   1.1.0
	 */
	public function getDnt(): int
	{
		return $this->dnt;
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
	 * @return  string  The HTML to load the JavaScript
	 * @since   1.1.0
	 *
	 */
	private function loadCommonJavascript($app, array $options = [])
	{
		// Prevent double inclusion of the JavaScript
		if ($this->haveIncludedJavaScript)
		{
			return '';
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

		$this->loadLanguage();

		$defaultOptions = [
			'accepted'                => $this->hasAcceptedCookies,
			'interacted'              => $this->hasCookiePreference,
			'cookie'                  => [
				'domain' => $domain,
				'path'   => $path,
			],
			'additionalCookieDomains' => $this->getAdditionalCookieDomains(),
			'whitelisted'             => $whiteList,
			'token'                   => $this->container->platform->getToken(),
			'resetNoticeText'         => JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_LBL_REMOVECOOKIES', true),
		];

		$options     = array_merge_recursive($defaultOptions, $options);
		$optionsJSON = json_encode($options, JSON_PRETTY_PRINT);

		$jsPath = $this->container->template->parsePath('media://plg_system_datacompliancecookie/js/datacompliancecookies.js');

		return <<< HTML
<script type="application/javascript">
	var AkeebaDataComplianceCookiesOptions = $optionsJSON;
</script>
<script type="application/javascript" src="$jsPath?{$this->container->mediaVersion}" defer="defer" async="async"></script>

HTML;

	}

	/**
	 * Load the common CSS for this plugin
	 *
	 * @param   CMSApplication  $app      The CMS application we are interfacing
	 * @param   array           $options  Additional options to pass to the JavaScript (overrides defaults)
	 *
	 * @return  string  The HTML to load the CSS
	 * @since   1.1.0
	 *
	 */
	private function loadCommonCSS($app, array $options = [])
	{
		// Prevent double inclusion of the CSS
		if ($this->haveIncludedCSS)
		{
			return '';
		}

		$this->haveIncludedCSS = true;

		$files = [];

		// Add our own CSS
		$files[] = $this->container->template->parsePath('media://plg_system_datacompliancecookie/css/datacompliancecookies.css') . '?' . $this->container->mediaVersion;

		// Filter out CSS files which have already been loaded
		$files = array_filter($files, function ($file) {
			$body = $this->app->getBody(false);

			return strpos($body, "href=\"$file\"") === false;
		});

		$ret = '';

		foreach ($files as $file)
		{
			$ret .= <<< HTML
<link rel="stylesheet" type="text/css" href="{$file}" />
 
HTML;

		}

		return $ret;
	}

	/**
	 * Load the HTML template used by our JavaScript for either the cookie acceptance banner or the post-acceptance
	 * cookie controls (revoke consent or reconsider declining cookies).
	 *
	 * @param   CMSApplication  $app  The CMS application we use to append the HTML output
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	private function loadHtml($app, $additionalContent = '')
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

		$fileName = $this->container->template->parsePath($template, true);

		ob_start();
		include $fileName;
		$content = ob_get_clean();

		if ($this->hasCookiePreference)
		{
			$template = 'plugin://system/datacompliancecookie/controls.php';
			$fileName = $this->container->template->parsePath($template, true);

			ob_start();
			include $fileName;
			$content .= ob_get_clean();
		}

		// Append the parsed view template content to the application's HTML output
		$body        = $app->getBody();
		$postBody    = '';
		$closeTagPos = strpos($body, '</body');

		if ($closeTagPos !== false)
		{
			$postBody = substr($body, $closeTagPos);
			$body     = substr($body, 0, $closeTagPos);
		}

		$app->setBody($body . $content . $additionalContent . $postBody);
	}

	/**
	 * Assign the current user to user groups depending on the cookie acceptance state.
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	private function applyUserGroupAssignments()
	{
		// Note that permanent user group assignment IS NOT possible for guest (not logged in) users
		$rejectGroup = $this->params->get('cookiesRejectedUserGroup', 0);
		$acceptGroup = $this->params->get('cookiesEnabledUserGroup', 0);
		$assignGroup = $this->hasAcceptedCookies ? $acceptGroup : $rejectGroup;

		// No group to assign. Bye-bye.
		if ($assignGroup == 0)
		{
			return;
		}

		DynamicGroups::addGroup($assignGroup);
		$this->reloadPlugins();
	}

	private function reloadPlugins()
	{
		// Get a reflection into JPluginHelper / PluginHelper
		try
		{
			$helperClass = class_exists('JPluginHelper') ? 'JPluginHelper' : 'Joomla\\CMS\\Plugin\\PluginHelper';
			$refHelper   = new ReflectionClass($helperClass);
		}
		catch (ReflectionException $e)
		{
			// Something broke
			return;
		}

		// Clear the protected static $plugins property through reflection
		$refPlugins = $refHelper->getProperty('plugins');
		$refPlugins->setAccessible(true);
		$refPlugins->setValue(null, null);

		// Reload the plugins list using the current authorized view levels
		$refMethod = $refHelper->getMethod('load');
		$refMethod->setAccessible(true);
		$refMethod->invoke(null);

		$allPlugins = $refPlugins->getValue();

		// The temp dispatcher is used to fire onAfterInitialize on any newly registered plugins
		$tempDispatcher = new JEventDispatcher();
		$dispatcher     = JEventDispatcher::getInstance();

		// Get the class names of all observers already registered
		$refDispatcher = new ReflectionObject($dispatcher);
		$refObservers  = $refDispatcher->getProperty('_observers');
		$refObservers->setAccessible(true);
		$observers     = $refObservers->getValue($dispatcher);
		$loadedPlugins = [];

		foreach ($observers as $o)
		{
			// Some observers are callables, not plugins
			if (!is_object($o))
			{
				continue;
			}

			$loadedPlugins[] = get_class($o);
		}

		/**
		 * Loop through all of the loaded plugins and retrieve the loaded plugin groups.
		 *
		 * Since we are called onAfterInitialize the only plugin group definitely loaded is 'system'. That's the only
		 * group Joomla! will import this early. However, the plugins themselves may load other plugin groups. That's
		 * why I need to parse the class names.
		 *
		 * NB! I cannot simply skip over anything starting with "PlgSystem" because that would also skip over plugin
		 *     groups whose name starts with system. For example, a plugin group "systemcall" would result in a class
		 *     name sich as "PlgSystemcallFoobar". You didn't see that coming, huh?
		 */
		$allowedPluginTypes = ['system'];

		foreach ($loadedPlugins as $className)
		{
			// Classname is PlgGroupPlugin where Group is the plugin group and Plugin is the plugin name
			$classNameParts = $this->container->inflector->explode($className);

			if (!isset($classNameParts[1]))
			{
				continue;
			}

			$group = strtolower($classNameParts[1]);

			$allowedPluginTypes[] = $group;
		}

		// Calling array_unique after the loop is faster than doing if(in_array($group, $allowedPluginTypes) in the loop
		$allowedPluginTypes = array_unique($allowedPluginTypes);

		/**
		 * PHP class names are case insensitive. Therefore we cast the loaded plugins to uppercase to make a
		 * case-insensitive search in the loop below.
		 */
		$loadedPlugins = array_map('strtoupper', $loadedPlugins);

		/**
		 * Loop through all system and other loaded plugins groups. We will import them if they are not already loaded.
		 * Then we will trigger onAfterIntialize through the $tempDispatcher, allowing the newly imported system plugins
		 * to initialize.
		 *
		 * Why only system plugins? Because this is called onAfterInitialize. At this point Joomla! has ONLY imported
		 * system plugins and dispatched the onAfterInitialize event. The newly imported system plugins did not have the
		 * chance to process onAfterIntialize so we need to dispatch that event ONLY to them. We cannot use the main
		 * application event dispatcher because that would call onAfterInitialize for the plugins already loaded. These
		 * plugins have the reasonable expectation that onAfterInitialize is only triggered ONCE per request. Hence the
		 * need for $tempDispatcher.
		 *
		 * Note that the next calls to JPluginHelper::import() will see the reloaded list of plugins. The reloaded list
		 * was built against the updated user group assignments to the current user, therefore the new view access
		 * levels.
		 *
		 * So, kids, this is how Greybeard Developers(tm) selectively reload plugins without screwing up your site by,
		 * say, blindly importing plugins in groups which have not been loaded yet such as plugin groups belonging to a
		 * component that's not even loaded.
		 */
		foreach ($allPlugins as $plugin)
		{
			/**
			 * The $allowedPluginTypes contains lowercase plugin groups, per Joomla! coding conventions. The $allPlugins
			 * array contains raw database data which MIGHT NOT be lowercase. Hence the need for strtolower.
			 */
			if (!in_array(strtolower($plugin->type), $allowedPluginTypes))
			{
				// The plugin is not of an allowed type.
				continue;
			}

			$plugin->type = preg_replace('/[^A-Z0-9_\.-]/i', '', $plugin->type);
			$plugin->name = preg_replace('/[^A-Z0-9_\.-]/i', '', $plugin->name);
			$path         = JPATH_PLUGINS . '/' . $plugin->type . '/' . $plugin->name . '/' . $plugin->name . '.php';

			if (!file_exists($path))
			{
				// The plugin file does not exist.
				continue;
			}

			$className = 'Plg' . $plugin->type . $plugin->name;

			/**
			 * Remember that we have converted the class names in $loadedPlugins to uppercase since PHP class names are
			 * case insensitive. Therefore we need to look if the array contains the uppercase class name of our plugin.
			 */
			if (in_array(strtoupper($className), $loadedPlugins))
			{
				// The plugin is already loaded.
				continue;
			}

			require_once $path;

			if (!class_exists($className))
			{
				// The plugin file does not contain the expected plugin class.
				continue;
			}

			// Load the plugin from the database.
			if (!isset($plugin->params))
			{
				// Seems like this could just go bye bye completely
				$plugin = call_user_func([$helperClass, 'getPlugin'], $plugin->type, $plugin->name);
			}

			// Instantiate and register the plugin with the main application dispatcher.
			$o = new $className($dispatcher, (array) $plugin);

			// Also register the plugin to the temporary dispatcher
			$tempDispatcher->attach($o);
		}

		// Let the newly imported plugins run their onAfterInitialize events
		$tempDispatcher->trigger('onAfterInitialize');
	}

	/**
	 * Returns the value of the HTTP DNT (Do Not Track) header.
	 *
	 * @return  int -1 if not set, 0 if DNT is disabled, 1 if enabled.
	 *
	 * @since   1.1.0
	 */
	private function getDNTValue(): int
	{
		if (isset($_SERVER['HTTP_DNT']))
		{
			return (int) $_SERVER['HTTP_DNT'];
		}

		if (function_exists('getallheaders'))
		{
			foreach (getallheaders() as $k => $v)
			{
				if (strtolower($k) === "dnt")
				{
					return (int) $v;
				}
			}
		}

		if (function_exists('getenv'))
		{
			$v = getenv('HTTP_DNT');

			if ($v !== false)
			{
				return (int) $v;
			}
		}

		return -1;
	}

	/**
	 * Create an audit log entry for the user's cookie preference
	 *
	 * @param   int   $preference  The recorded preference.
	 * @param   int   $dnt         The value of the Do Not Track header (-1 means not set, -2 means does not apply)
	 * @param   bool  $reset       Did the user ask for his preference to be reset? If so, the recorded preference is
	 *                             the applied default value.
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	private function logAuditTrail(int $preference, int $dnt, bool $reset)
	{
		try
		{
			/** @var Cookietrails $model */
			$model = $this->container->factory->model('Cookietrails')->tmpInstance();
			$model->create([
				'preference' => $preference,
				'dnt'        => $dnt,
				'reset'      => $reset ? 1 : 0,
			]);
		}
		catch (Exception $e)
		{
			// No worries if that fails
		}
	}
}
