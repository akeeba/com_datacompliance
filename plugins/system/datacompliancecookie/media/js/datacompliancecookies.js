/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Akeeba Data Compliance - Cookies compliance JavaScript class
 *
 * @param   {object}  options  Configuration options
 * @constructor
 */
var AkeebaDataComplianceCookies = function (options)
{
	var me = this;

	this.vars = {
		// Has the user already accepted cookies?
		accepted: false,
		// Has the user already interacted with the prompt (accepting or declining cookies)?
		interacted: false,
		// The cookie domain and path to use when unsetting cookies
		cookie: {
			domain: null,
			path:   null
		},
		// Additional domain names to use when unsetting cookies
		additionalCookieDomains: [],
		// Whitelisted cookie names
		whitelisted: []
	};

	var construct = function (options)
	{
		Object.assign(me.vars, options);
	};

	/**
	 * Disable JavaScript storage (cookies, session and local storage)
	 */
	this.disableStorage = function ()
	{
		me.removeKnownCookies();

		me.disableJavascriptCookies();

		me.disableLocalStorage();

		me.disableSessionStorage();
	};

	/**
	 * Find the cookies which are currently known to the browser and unset them
	 */
	this.removeKnownCookies = function ()
	{
		var cookies = me.Cookies.get();

		for (var cookieName in cookies)
		{
			if (!cookies.hasOwnProperty(cookieName))
			{
				continue;
			}

			// Skip whitelisted cookies
			if (me.vars.whitelisted.indexOf(cookieName) > -1)
			{
				continue;
			}

			// Remove the cookie from the browser
			me.removeCookie(cookieName);
		}
	};

	/**
	 * Remove a cookie
	 *
	 * @param   {string}  cookieName  The name of the cookie to remove
	 */
	this.removeCookie = function(cookieName)
	{
		me.Cookies.remove(cookieName, { path: me.vars.cookie.path, domain: me.vars.cookie.domain });

		if (!me.vars.additionalCookieDomains.length)
		{
			return;
		}

		for (var domainName in me.vars.additionalCookieDomains)
		{
			if (!me.vars.additionalCookieDomains.hasOwnProperty(domainName))
			{
				continue;
			}

			me.Cookies.remove(cookieName, { path: me.vars.cookie.path, domain: domainName });
		}
	};

	/**
	 * Disable setting and getting cookies through JavaScript
	 */
	this.disableJavascriptCookies = function ()
	{
		// Modern browsers (even including IE8 and up): override the document.cookie getter / setter
		if (document.__defineGetter__)
		{
			document.__defineGetter__("cookie", function ()
			{
				return '';
			});

			document.__defineSetter__("cookie", function ()
			{
			});

			return;
		}

		// Legacy browsers (IE 6 and 7)
		if (navigator.appVersion.indexOf("MSIE 6.") === -1 || navigator.appVersion.indexOf("MSIE 7.") === -1)
		{
			Object.defineProperty(document, 'cookie', {
				get: function ()
					 {
						 return '';
					 },
				set: function ()
					 {
						 return true;
					 }
			});
		}
	};

	/**
	 * Disable JavaScript's local storage
	 */
	this.disableLocalStorage = function ()
	{
		window.localStorage.clear();
		window.localStorage.__proto__         = Object.create(window.Storage.prototype);
		window.localStorage.__proto__.setItem = function ()
		{
			return undefined;
		};
	};

	/**
	 * Disable JavaScript's session storage
	 */
	this.disableSessionStorage = function ()
	{
		window.sessionStorage.clear();
		window.sessionStorage.__proto__         = Object.create(window.Storage.prototype);
		window.sessionStorage.__proto__.setItem = function ()
		{
			return undefined;
		};
	};

	/**
	 * Javascript implementation of base64_decode.
	 *
	 * @param   {string}  encodedData
	 * @returns {string}
	 *
	 * @see     http://locutus.io/php/url/base64_decode/
	 */
	this.base64_decode = function (encodedData)
	{
		var decodeUTF8string = function (str)
		{
			// Going backwards: from bytestream, to percent-encoding, to original string.
			return decodeURIComponent(str.split('').map(function (c)
			{
				return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
			}).join(''))
		};

		if (typeof window !== 'undefined')
		{
			if (typeof window.atob !== 'undefined')
			{
				return decodeUTF8string(window.atob(encodedData))
			}
		}
		else
		{
			return new Buffer(encodedData, 'base64').toString('utf-8')
		}

		var b64    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
		var o1;
		var o2;
		var o3;
		var h1;
		var h2;
		var h3;
		var h4;
		var bits;
		var i      = 0;
		var ac     = 0;
		var dec    = '';
		var tmpArr = [];

		if (!encodedData)
		{
			return encodedData
		}

		encodedData += '';

		do
		{
			// unpack four hexets into three octets using index points in b64
			h1 = b64.indexOf(encodedData.charAt(i++));
			h2 = b64.indexOf(encodedData.charAt(i++));
			h3 = b64.indexOf(encodedData.charAt(i++));
			h4 = b64.indexOf(encodedData.charAt(i++));

			bits = h1 << 18 | h2 << 12 | h3 << 6 | h4;

			o1 = bits >> 16 & 0xff;
			o2 = bits >> 8 & 0xff;
			o3 = bits & 0xff;

			if (h3 === 64)
			{
				tmpArr[ac++] = String.fromCharCode(o1)
			}
			else if (h4 === 64)
			{
				tmpArr[ac++] = String.fromCharCode(o1, o2)
			}
			else
			{
				tmpArr[ac++] = String.fromCharCode(o1, o2, o3)
			}
		} while (i < encodedData.length);

		dec = tmpArr.join('');

		return decodeUTF8string(dec.replace(/\0+$/, ''));
	};

	construct(options);
};

/*!
 * JavaScript Cookie v2.2.0
 * https://github.com/js-cookie/js-cookie
 *
 * Copyright 2006, 2015 Klaus Hartl & Fagner Brack
 * Released under the MIT license
 */
;(function (factory) {
	var registeredInModuleLoader = false;
	if (typeof define === 'function' && define.amd) {
		define(factory);
		registeredInModuleLoader = true;
	}
	if (typeof exports === 'object') {
		module.exports = factory();
		registeredInModuleLoader = true;
	}
	if (!registeredInModuleLoader) {
		var OldCookies = window.Cookies;
		var api = window.Cookies = factory();
		api.noConflict = function () {
			window.Cookies = OldCookies;
			return api;
		};
	}
}(function () {
	function extend () {
		var i = 0;
		var result = {};
		for (; i < arguments.length; i++) {
			var attributes = arguments[ i ];
			for (var key in attributes) {
				result[key] = attributes[key];
			}
		}
		return result;
	}

	function init (converter) {
		function api (key, value, attributes) {
			var result;
			if (typeof document === 'undefined') {
				return;
			}

			// Write

			if (arguments.length > 1) {
				attributes = extend({
					path: '/'
				}, api.defaults, attributes);

				if (typeof attributes.expires === 'number') {
					var expires = new Date();
					expires.setMilliseconds(expires.getMilliseconds() + attributes.expires * 864e+5);
					attributes.expires = expires;
				}

				// We're using "expires" because "max-age" is not supported by IE
				attributes.expires = attributes.expires ? attributes.expires.toUTCString() : '';

				try {
					result = JSON.stringify(value);
					if (/^[\{\[]/.test(result)) {
						value = result;
					}
				} catch (e) {}

				if (!converter.write) {
					value = encodeURIComponent(String(value))
						.replace(/%(23|24|26|2B|3A|3C|3E|3D|2F|3F|40|5B|5D|5E|60|7B|7D|7C)/g, decodeURIComponent);
				} else {
					value = converter.write(value, key);
				}

				key = encodeURIComponent(String(key));
				key = key.replace(/%(23|24|26|2B|5E|60|7C)/g, decodeURIComponent);
				key = key.replace(/[\(\)]/g, escape);

				var stringifiedAttributes = '';

				for (var attributeName in attributes) {
					if (!attributes[attributeName]) {
						continue;
					}
					stringifiedAttributes += '; ' + attributeName;
					if (attributes[attributeName] === true) {
						continue;
					}
					stringifiedAttributes += '=' + attributes[attributeName];
				}
				return (document.cookie = key + '=' + value + stringifiedAttributes);
			}

			// Read

			if (!key) {
				result = {};
			}

			// To prevent the for loop in the first place assign an empty array
			// in case there are no cookies at all. Also prevents odd result when
			// calling "get()"
			var cookies = document.cookie ? document.cookie.split('; ') : [];
			var rdecode = /(%[0-9A-Z]{2})+/g;
			var i = 0;

			for (; i < cookies.length; i++) {
				var parts = cookies[i].split('=');
				var cookie = parts.slice(1).join('=');

				if (!this.json && cookie.charAt(0) === '"') {
					cookie = cookie.slice(1, -1);
				}

				try {
					var name = parts[0].replace(rdecode, decodeURIComponent);
					cookie = converter.read ?
						converter.read(cookie, name) : converter(cookie, name) ||
						cookie.replace(rdecode, decodeURIComponent);

					if (this.json) {
						try {
							cookie = JSON.parse(cookie);
						} catch (e) {}
					}

					if (key === name) {
						result = cookie;
						break;
					}

					if (!key) {
						result[name] = cookie;
					}
				} catch (e) {}
			}

			return result;
		}

		api.set = api;
		api.get = function (key) {
			return api.call(api, key);
		};
		api.getJSON = function () {
			return api.apply({
				json: true
			}, [].slice.call(arguments));
		};
		api.defaults = {};

		api.remove = function (key, attributes) {
			api(key, '', extend(attributes, {
				expires: -1
			}));
		};

		api.withConverter = init;

		return api;
	}

	return init(function () {});
}));

/**
 * document.ready equivalent
 *
 * @see  https://github.com/jfriend00/docReady/blob/master/docready.js
 */
var AkeebaDataComplianceCookiesOnDocumentReady = function (funcName, baseObj)
{
	funcName = funcName || "documentReady";
	baseObj  = baseObj || window.AkeebaDataComplianceCookies;

	var readyList                   = [];
	var readyFired                  = false;
	var readyEventHandlersInstalled = false;

	// Call this when the document is ready. This function protects itself against being called more than once.
	function ready()
	{
		if (!readyFired)
		{
			// This must be set to true before we start calling callbacks
			readyFired = true;

			for (var i = 0; i < readyList.length; i++)
			{
				/**
				 * If a callback here happens to add new ready handlers, this function will see that it already
				 * fired and will schedule the callback to run right after this event loop finishes so all handlers
				 * will still execute in order and no new ones will be added to the readyList while we are
				 * processing the list.
				 */
				readyList[i].fn.call(window, readyList[i].ctx);
			}

			// Allow any closures held by these functions to free
			readyList = [];
		}
	}

	/**
	 * Solely for the benefit of Internet Explorer
	 */
	function readyStateChange()
	{
		if (document.readyState === "complete")
		{
			ready();
		}
	}

	/**
	 * This is the one public interface:
	 *
	 * window.AkeebaDataComplianceCookies.documentReady(fn, context);
	 *
	 * @param   callback   The callback function to execute when the document is ready.
	 * @param   context    Optional. If present, it will be passed as an argument to the callback.
	 */
	baseObj[funcName] = function (callback, context)
	{
		// If ready() has already fired, then just schedule the callback to fire asynchronously
		if (readyFired)
		{
			setTimeout(function ()
			{
				callback(context);
			}, 1);

			return;
		}

		// Add the function and context to the queue
		readyList.push({fn: callback, ctx: context});

		/**
		 * If the document is already ready, schedule the ready() function to run immediately.
		 *
		 * Note: IE is only safe when the readyState is "complete", other browsers are safe when the readyState is
		 * "interactive"
		 */
		if (document.readyState === "complete" || (!document.attachEvent && document.readyState === "interactive"))
		{
			setTimeout(ready, 1);

			return;
		}

		// If the handlers are already installed just quit
		if (readyEventHandlersInstalled)
		{
			return;
		}

		// We don't have event handlers installed, install them
		readyEventHandlersInstalled = true;

		// -- We have an addEventListener method in the document, this is a modern browser.

		if (document.addEventListener)
		{
			// Prefer using the DOMContentLoaded event
			document.addEventListener("DOMContentLoaded", ready, false);

			// Our backup is the window's "load" event
			window.addEventListener("load", ready, false);

			return;
		}

		// -- Most likely we're stuck with an ancient version of IE

		// Our primary method of activation is the onreadystatechange event
		document.attachEvent("onreadystatechange", readyStateChange);

		// Our backup is the windows's "load" event
		window.attachEvent("onload", ready);
	}
};

/**
 * Polyfill for Object.assign
 *
 * @see  https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/assign#Polyfill
 */
if (typeof Object.assign != 'function') {
	// Must be writable: true, enumerable: false, configurable: true
	Object.defineProperty(Object, "assign", {
		value: function assign(target, varArgs) { // .length of function is 2
			'use strict';
			if (target == null) { // TypeError if undefined or null
				throw new TypeError('Cannot convert undefined or null to object');
			}

			var to = Object(target);

			for (var index = 1; index < arguments.length; index++) {
				var nextSource = arguments[index];

				if (nextSource != null) { // Skip over if undefined or null
					for (var nextKey in nextSource) {
						// Avoid bugs when hasOwnProperty is shadowed
						if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
							to[nextKey] = nextSource[nextKey];
						}
					}
				}
			}
			return to;
		},
		writable: true,
		configurable: true
	});
}

window.AkeebaDataComplianceCookies = new AkeebaDataComplianceCookies(AkeebaDataComplianceCookiesOptions);
AkeebaDataComplianceCookiesOnDocumentReady('documentReady', window.AkeebaDataComplianceCookies);
window.AkeebaDataComplianceCookies.Cookies = Cookies.noConflict();

// Set up the document's ready event handler
AkeebaDataComplianceCookies.documentReady(function ()
{
	// If the user has not accepted cookies for this site we should block them
	if (!window.AkeebaDataComplianceCookies.vars.accepted)
	{
		window.AkeebaDataComplianceCookies.disableStorage();
	}

	// TODO If the user has made no preference display the modal or the cookie controls
	if (!window.AkeebaDataComplianceCookies.vars.interacted)
	{
		// TODO Show cookies modal

		return;
	}

	// The user has already interacted. We do NOT show the modal but we DO show them the cookie controls
	if (window.AkeebaDataComplianceCookies.vars.accepted)
	{
		// TODO Show controls to disable cookies
	}
	else
	{
		// TODO Show controls to display the modal again
	}
});