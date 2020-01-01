<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

/**
 * Class plgSystemDataComplianceCookieHelper
 *
 * @since 1.1.0
 */
abstract class plgSystemDataComplianceCookieHelper
{
	/**
	 * Joomla's session cookie name
	 *
	 * @var  null|string
	 *
	 * @since   1.1.0
	 */
	private static $sessionCookieName = null;

	/**
	 * The name of our helper's cookie. This is set on the client's browser to record their cookie preference.
	 *
	 * @var  string
	 *
	 * @since   1.1.0
	 */
	private static $helperCookieName = 'plg_system_datacompliancecookie';

	/**
	 * Has the current user accepted cookies? Use the method hasAcceptedCookies() to retrieve its value.
	 *
	 * @var  null|bool
	 *
	 * @since   1.1.0
	 */
	private static $hasAcceptedCookies = null;

	/**
	 * Sets the name of our helper cookie. This is set on the client's browser to record their cookie preference.
	 *
	 * @param   string  $cookieName  The name of the cookie. An empty value results in the default being used.
	 *
	 * @since   1.1.0
	 */
	public static function setCookieName($cookieName)
	{
		if (empty($cookieName))
		{
			$cookieName = 'plg_system_datacompliancecookie';
		}

		self::$helperCookieName = $cookieName;
	}

	/**
	 * Gets the name of our helper cookie. This is set on the client's browser to record their cookie preference.
	 *
	 * @return  string
	 *
	 * @since   1.1.0
	 */
	public static function getCookieName(): string
	{
		return self::$helperCookieName;
	}

	/**
	 * Returns the contents of the helper cookie. If the cookie is not set or is invalid we return boolean false.
	 *
	 * @return  bool|array
	 *
	 * @since   1.1.0
	 */
	public static function getDecodedCookieValue()
	{
		// Try to get the cookie.
		try
		{
			$cookie = JFactory::getApplication()->input->cookie->get(self::$helperCookieName, null);
		}
		catch (Exception $e)
		{
			return false;
		}

		// No cookie? Then the user has not accepted cookies from our site.
		if (is_null($cookie) || empty(trim($cookie)))
		{
			return false;
		}

		// Try to decode the cookie content.
		$cookie = base64_decode($cookie);

		// Failed to decode? Then the user has not accepted cookies from our site.
		if (is_null($cookie) || empty(trim($cookie)))
		{
			return false;
		}

		// Decode the JSON-encoded cookie data/
		$cookie = @json_decode($cookie, true);

		// Failed to decode? Then the user has not accepted cookies from our site.
		if (is_null($cookie))
		{
			return false;
		}

		return $cookie;
	}

	/**
	 * Has the user accepted cookies and indicated so by storing a cookie on their browsers?
	 *
	 * @return  bool
	 *
	 * @since   1.1.0
	 */
	public static function hasAcceptedCookies($defaultState = false): bool
	{
		if (is_null(self::$hasAcceptedCookies))
		{
			try
			{
				// If the user does not have a recorded preference I will return the supplied default state
				$hasCookie = self::getDecodedCookieValue() !== false;

				self::$hasAcceptedCookies = $hasCookie ? self::decodeAcceptanceFromHelperCookie() : $defaultState;
			}
			catch (Exception $e)
			{
				self::$hasAcceptedCookies = false;
			}
		}

		return self::$hasAcceptedCookies;
	}

	/**
	 * Mark the acceptance or rejection of cookies by setting a cookie in the browser. If the user has rejected cookies
	 * there is, indeed, a cookie set on their browser to indicate their preference. This seems to be allowed. After all
	 * how would you know if someone has rejected cookies or simply not made a preference if you do not use cookies to
	 * remember their preference? :)
	 *
	 * @param   bool  $accepted         Has the user accepted cookies from our site?
	 * @param   int   $forThisManyDays  For how many days will the acceptance be remembered (default: 90)
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	public static function setAcceptedCookies(bool $accepted, int $forThisManyDays = 90)
	{
		self::$hasAcceptedCookies = $accepted;

		// Try to get the application reference
		try
		{
			$app = JFactory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		// The cookie is valid for $forThisManyDays days
		try
		{
			$expires = new DateTime();
			$expires->add(new DateInterval('P' . $forThisManyDays . 'D'));
		}
		catch (Exception $e)
		{
			$expires = new DateTime(time() + 86400 * $forThisManyDays);
		}

		// Set up the cookie values
		$cookieValues = [
			'accept' => $accepted,
			'until'  => $expires->getTimestamp(),
		];

		// Set the cookie
		$encodedCookieValue = base64_encode(json_encode($cookieValues));
		$cookieExpiration   = $expires->format('U');
		$path               = $app->get('cookie_path', '/');
		$domain             = $app->get('cookie_domain', filter_input(INPUT_SERVER, 'HTTP_HOST'));
		$secure             = $app->get('force_ssl', 0) == 2;
		$httpOnly           = true;

		$app->input->cookie->set(self::$helperCookieName, $encodedCookieValue, $cookieExpiration, $path, $domain, $secure, $httpOnly);
	}

	/**
	 * Ask the browser to unset the helper cookie which marks the user's cookie preferences.
	 *
	 * @param   bool   $defaultState  The default cookie acceptance state when a preference is not yet recorded
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	public static function removeCookiePreference(bool $defaultState = false)
	{
		self::$hasAcceptedCookies = $defaultState;

		// Try to get the application reference
		try
		{
			/** @var JApplicationSite $app */
			$app = JFactory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		// Set the cookie
		$path               = $app->get('cookie_path', '/');
		$domain             = $app->get('cookie_domain', filter_input(INPUT_SERVER, 'HTTP_HOST'));
		$secure             = $app->get('force_ssl', 0) == 2;
		$httpOnly           = true;

		$app->input->cookie->set(self::$helperCookieName, '', 1, $path, $domain, $secure, $httpOnly);
	}

	/**
	 * Remove all cookies known by the server. Please note that third party cookies cannot be removed. Moreover, any new
	 * cookies that the server is attempting to set are also removed from the response.
	 *
	 * @param   bool   $allowSessionCookie  Should the session cookie be exempt from deletion?
	 * @param   array  $domainNames         Which domain names are cookies set for?
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	public static function unsetAllCookies(bool $allowSessionCookie = true, array $domainNames = [])
	{
		// If no additional domain names are set I will add my default ones
		if (empty($domainNames))
		{
			$domainNames = self::getDefaultCookieDomainNames();
		}

		// Get a cookie whitelist. I need to whitelist at least the helper cookie.
		$whiteList = [self::$helperCookieName];

		// If the session cookie is allowed I need to whitelist it too.
		if ($allowSessionCookie)
		{
			$whiteList[] = self::getSessionCookieName();
			$whiteList[] = 'joomla_user_state';
		}

		/**
		 * There are two sets of cookies we need to remove:
		 *
		 * 1. Already set cookies per $_COOKIE
		 *
		 * 2. Cookies to be set, per Set-Cookies headers as reported by headers_list()
		 *
		 * Each case is dealt with separately.
		 *
		 * Set-Cookie: 0acb690ef3882a15acbe3ffe5c7797e6=14214d17f1e98ff29213db27bde97fab; path=/; secure; HttpOnly
		 * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie
		 */

		self::unsetNewCookies($whiteList);
		self::unsetExistingCookies($whiteList, $domainNames);
	}

	/**
	 * Unset cookies which have already been set and Joomla! is aware of.
	 *
	 * @param   array  $whiteList    A list of cookie names which will not be unset
	 * @param   array  $domainNames  Additional domain names to use when unsetting existing cookies
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	private static function unsetExistingCookies(array $whiteList, array $domainNames)
	{
		/**
		 * First get the cookie names using PHP's $_COOKIE superglobal.
		 *
		 * This works and is more reliable BUT if there are cookies in array notation (foo[bar]) the get converted into
		 * an array which kinda sucks for us. See below.
		 */
		$cookieInput = new \FOF30\Input\Input($_COOKIE);
		$cookies = $cookieInput->getData();

		if (empty($cookies))
		{
			return;
		}

		$cookieNames = array_keys($cookies);

		/**
		 * For the second pass I will use $_SERVER['HTTP_COOKIE'] which lets me unset cookies also in array notation.
		 */
		if (isset($_SERVER['HTTP_COOKIE']))
		{
			$cookies = explode(';', $_SERVER['HTTP_COOKIE']);

			foreach ($cookies as $cookie)
			{
				$parts = explode('=', $cookie);
				$cookieNames[] = trim($parts[0]);
			}
		}

		/**
		 * Now I need to get the unique cookie names
		 */
		$cookieNames = array_unique($cookieNames);

		/**
		 * Loop through all the cookies and unset them.
		 */
		foreach ($cookieNames as $cookieName)
		{
			$cookieName = trim($cookieName);

			if (in_array($cookieName, $whiteList))
			{
				continue;
			}

			if (!empty($cookieName) && is_string($cookieName))
			{
				if (!is_array($domainNames))
				{
					if (is_string($domainNames) && empty($domainNames))
					{
						$domainNames = array($domainNames);
					}
					else
					{
						$domainNames = array();
					}
				}

				self::unsetCookieFromAllDomains($cookieName, $domainNames);
			}

		}
	}

	/**
	 * Unsets any cookies the page is trying to send to the browser.
	 *
	 * @param   array  $whiteList    A list of cookie names which will not be unset
	 *
	 * @return  void
	 *
	 * @since   1.1.0
	 */
	private static function unsetNewCookies(array $whiteList)
	{
		// Keep a copy of the headers
		$headers = headers_list();

		// Remove all Set-Cookie headers
		header_remove('Set-Cookie');

		/**
		 * In order to allow the session cookie we have to loop through all Set-Cookie headers, identify the one trying
		 * to set one of the whitelisted cookies and re-apply it.
		 */
		foreach ($headers as $header)
		{
			$header = ltrim($header);

			// Too short to be a Set-Cookie header?
			if (strlen($header) < 10)
			{
				continue;
			}

			// Does it look like a set-cookie header?
			$leftTen = strtolower(substr($header, 0, 10));

			if ($leftTen != 'set-cookie')
			{
				continue;
			}

			$parts = explode(':', $header, 2);

			if (strtolower($parts[0]) != 'set-cookie')
			{
				// False match. Grunt.
				continue;
			}

			$headerValue = $parts[1];
			$valueParts  = explode(';', $headerValue, 2);
			list ($cookieName, $cookieValue) = explode('=', trim($valueParts[0]));

			// Is this a whitelisted cookie?
			if (!in_array($cookieName, $whiteList))
			{
				continue;
			}

			/**
			 * Set the header once more.
			 *
			 * Why not use setcookie() instead? Well, that would require parsing the HTTP header fully in order to
			 * understand if the secure or httpOnly options are set and figure out the cookie domain and path. However
			 * this is just too much work when we just need to undo our previous mass-unsetting of the Set-Cookie
			 * header. Keep it simple!
			 */
			header('Set-Cookie: ' . $headerValue, false);
		}
	}

	/**
	 * Remove a cookie from all domain names
	 *
	 * @param   string  $cookieName   The cookie to remove
	 * @param   array   $domainNames  The domain names to remove it from
	 *
	 * @since   1.1.0
	 */
	private static function unsetCookieFromAllDomains($cookieName, array $domainNames)
	{
		try
		{
			$app = JFactory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		$cookiePath   = $app->get('cookie_path', '/');
		$cookieDomain = $app->get('cookie_domain', filter_input(INPUT_SERVER, 'HTTP_HOST'));

		if (!empty($cookieName) && is_string($cookieName))
		{
			if (empty($cookiePath) && !is_string($cookiePath))
			{
				$cookiePath = '';
			}

			if (!empty($cookieDomain) && is_string($cookieDomain))
			{
				self::unsetCookie($cookieName, $cookiePath, $cookieDomain);
			}
		}

		// Do I have additional domain names?
		if (empty($domainNames))
		{
			return;
		}

		foreach ($domainNames as $domainName)
		{
			// Skip additional domain names which are the same as the domain name I just processed (unnecessary work)
			if ($domainName == $cookieDomain)
			{
				continue;
			}

			// Rerun myself with just one additional domain
			if (!empty($cookieName) && is_string($cookieName))
			{
				if (empty($cookiePath) && !is_string($cookiePath))
				{
					$cookiePath = '';
				}

				if (!empty($cookieDomain) && is_string($cookieDomain))
				{
					self::unsetCookie($cookieName, $cookiePath, $cookieDomain);
				}
			}
		}
	}

	/**
	 * Fully unset a cookie from the given cookie path and domain name.
	 *
	 * We are going to automatically try different combinations of secure and HTTP-only flags since this is required to
	 * successfully unset a cookie. Unsetting the cookie happens by setting its value to an empty string and its
	 * expiration time to a year in the past.
	 *
	 * @param   string  $cookieName    The name of the cookie to unset
	 * @param   string  $cookiePath    The path for the cookie
	 * @param   string  $cookieDomain  The domain name for the cookie.
	 *
	 * @since   1.1.0
	 */
	private static function unsetCookie(string $cookieName, string $cookiePath, string $cookieDomain)
	{
		try
		{
			$app = JFactory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		/**
		 * The best way to expire a cookie is to set its expiration time to a year ago from now. DO NOT USE 0. Zero has
		 * a special meaning (expire at the end of the browser session).
		 *
		 * CAVEAT: Each cookie must be unset with the same combination of parameters (path, domain, secure, httpOnly) it
		 * was originally set. Hence the need to loop through the values.
		 */
		$yearAgo      = time() - 365 * 86400;

		foreach ([true, false] as $secure)
		{
			foreach ([true, false] as $httpOnly)
			{
				$app->input->cookie->set($cookieName, '', $yearAgo, '', '', $secure, $httpOnly);
				$app->input->cookie->set($cookieName, '', $yearAgo, $cookiePath, $cookieDomain, $secure, $httpOnly);
			}
		}
	}

	/**
	 * Check if there is a cookie set with the cookie acceptance preferences and whether it is still valid. Return the
	 * results as a boolean expression.
	 *
	 * @return  bool
	 * @throws  Exception  Thrown if an error occurs.
	 *
	 * @since   1.1.0
	 */
	private static function decodeAcceptanceFromHelperCookie()
	{
		// Try to get the decoded cookie values.
		$cookie = self::getDecodedCookieValue();

		if ($cookie === false)
		{
			return false;
		}

		// Has the user indicated that they accept cookies?
		if (!array_key_exists('accept', $cookie))
		{
			return false;
		}

		if (!$cookie['accept'])
		{
			return false;
		}

		// Is the cookie acceptance validity date in the future?
		$until = array_key_exists('until', $cookie) ? $cookie['until'] : 0;
		$dateUntil = new \FOF30\Date\Date($until);

		if ($dateUntil->toUnix() < time())
		{
			return false;
		}

		// All checks complete, the user has accepted cookies.
		return true;
	}

	/**
	 * Return the name of Joomla's session cookie
	 *
	 * @return  string
	 *
	 * @since   1.1.0
	 */
	public static function getSessionCookieName(): string
	{
		if (is_null(self::$sessionCookieName))
		{
			try
			{
				$session = JFactory::getApplication()->getSession();

				self::$sessionCookieName = $session->getName();
			}
			catch (Exception $e)
			{
				self::$sessionCookieName = '';
			}
		}

		return self::$sessionCookieName;
	}

	/**
	 * Get the default list of domain names for cookies
	 *
	 * @return array
	 *
	 * @since   1.1.0
	 */
	public static function getDefaultCookieDomainNames(): array
	{
		$domainNames = [];

		/**
		 * First, we are going to add the current domain name as defined in the HOST header.
		 *
		 * We add the full domain (e.g. www.example.com), its base domain (example.com) and the dotted base domain
		 * (.example.com). The latter catches all other subdomains and is commonly used with things like Google
		 * Analytics.
		 */
		$defaultDomain = filter_input(INPUT_SERVER, 'HTTP_HOST');

		if (empty($defaultDomain))
		{
			$defaultDomain = \Joomla\CMS\Uri\Uri::getInstance()->toString(['host']);
		}

		$domainNames[] = $defaultDomain;
		$domainNames[] = self::getBaseDomain($defaultDomain);
		$domainNames[] = '.' . self::getBaseDomain($defaultDomain);

		/**
		 * Next up, we're going to get Joomla's cookie domain name which might be different.
		 *
		 * We add the full domain (e.g. www.example.com), its base domain (example.com) and the dotter base domain
		 * (.example.com). The latter catches all other subdomains and is commonly used with things like Google
		 * Analytics.
		 */
		try
		{
			$app           = JFactory::getApplication();
			$jCookieDomain = $app->get('cookie_domain', $defaultDomain);
			$domainNames[] = $jCookieDomain;
			$domainNames[] = self::getBaseDomain($jCookieDomain);
			$domainNames[] = '.' . self::getBaseDomain($jCookieDomain);
		}
		catch (Exception $e)
		{
		}

		return array_unique($domainNames);
	}

	/**
	 * Get the base domain of a subdomain. This is the domain and TLD part, ignoring all subdomain parts.
	 *
	 * For example, given www.example.com we return example.com. Given foo.bar.baz.example.com we also return
	 * example.com.
	 *
	 * @param   string  $subdomain
	 *
	 * @return  string
	 *
	 * @since   1.1.0
	 */
	private static function getBaseDomain(string $subdomain): string
	{
		$domainParts = explode('.', $subdomain);

		if (count($domainParts) > 2)
		{
			$ret = array_pop($domainParts);
			$ret = array_pop($domainParts) . '.' . $ret;

			return $ret;
		}

		return $subdomain;
	}
}