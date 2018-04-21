<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

class plgSystemDatacompliance extends JPlugin
{
	/**
	 * The Joomla! CMS application
	 *
	 * @var  \Joomla\CMS\Application\CMSApplication
	 */
	protected $app;

	public function __construct($subject, array $config = array())
	{
		$this->autoloadLanguage = true;

		parent::__construct($subject, $config);
	}

	public function onBeforeRender()
	{
		$this->loadCookieConsent();
	}

	/**
	 * Load the Cookie Consent JS and CSS when our page has HTML output and we're in the site's frontend.
	 */
	private function loadCookieConsent()
	{
		if ($this->app->input->getCmd('format', 'html') != 'html')
		{
			return;
		}

		if ($this->app->isClient('administrator'))
		{
			return;
		}

		$mediaVersion = md5(DATACOMPLIANCE_VERSION . $this->app->get('secret'));

		// Load the JS. We set framework=true to force Joomla to load jQuery before us
		JHtml::_('script', 'plg_system_datacompliance/cookieconsent.min.js', [
			'version'       => $mediaVersion,
			'relative'      => true,
			'detectDebug'   => true,
			'framework'     => true,
			'pathOnly'      => false,
			'detectBrowser' => false,
		], [
			'mime'  => 'text/javascript',
			'defer' => false,
			'async' => false,
		]);

		// Load the default Cookie Consent CSS
		// TODO Do not load the default CSS if an option to do that is set
		JHtml::_('stylesheet', 'plg_system_datacompliance/cookieconsent.min.css', array(
			'version'       => $mediaVersion,
			'relative'      => true,
			'detectDebug'   => true,
			'pathOnly'      => false,
			'detectBrowser' => true,
		), array(
			'type' => 'text/css',
		));

		// Load our common cookie consent JS.
		// TODO Minify
		JHtml::_('script', 'plg_system_datacompliance/cookie_common.js', [
			'version'       => $mediaVersion,
			'relative'      => true,
			'detectDebug'   => true,
			'framework'     => true,
			'pathOnly'      => false,
			'detectBrowser' => false,
		], [
			'mime'  => 'text/javascript',
			'defer' => false,
			'async' => false,
		]);

		/**
		 * Load the Cookie Consent configuration.
		 *
		 * This file can be overridden by copying media/plg_system_datacompliance/js/cookie_init.js to
		 * templates/YOURTEMPLATE/js/plg_system_datacompliance/cookie_init.js and editing the new file. Refer to
		 * https://cookieconsent.insites.com/documentation/javascript-api/ for all the available options
		 */
		// TODO Minify
		JHtml::_('script', 'plg_system_datacompliance/cookie_init.js', [
			'version'       => $mediaVersion,
			'relative'      => true,
			'detectDebug'   => true,
			'framework'     => true,
			'pathOnly'      => false,
			'detectBrowser' => false,
		], [
			'mime'  => 'text/javascript',
			'defer' => false,
			'async' => false,
		]);

		// Check every 200msec until the CookieConsent JS and its configuration are fully loaded (workaround for slow connections)
		$js = <<< JS
var akeebaDataComplianceInitTimer = null;
		
window.addEventListener("load", function() {
	akeebaDataComplianceInitTimer = window.setInterval(function() {
		if (typeof akeeba === "undefined") { return; }
		if (typeof akeeba.DataCompliance === "undefined") { return; }
		if (typeof akeeba.DataCompliance.cookieConsentOptions === "undefined") { return; }
		if (typeof window.cookieconsent === "undefined") { return; }
		window.clearInterval(akeebaDataComplianceInitTimer);
		window.cookieconsent.initialise(akeeba.DataCompliance.cookieConsentOptions);
	}, 200);
	
});
JS;

		$this->app->getDocument()->addScriptDeclaration($js);
	}
}