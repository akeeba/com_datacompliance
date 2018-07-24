var AkeebaDataComplianceCookies = function (options)
{
	var me = this;

	this.vars = {
		cookie: {
			domain: null,
			path:   null
		},
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
		// TODO I need to check which cookies are currently known to the browser and expire them

		me.disableJavascriptCookies();

		me.disableLocalStorage();

		me.disableSessionStorage();
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

// Construct and initialise the Javascript object for this plugin.
if (typeof AkeebaDataComplianceCookiesOptions === 'undefined')
{
	var AkeebaDataComplianceCookiesOptions = {};
}

window.AkeebaDataComplianceCookies = new AkeebaDataComplianceCookies(AkeebaDataComplianceCookiesOptions);
AkeebaDataComplianceCookiesOnDocumentReady('documentReady', window.AkeebaDataComplianceCookies);

// Set up the document's ready event handler
AkeebaDataComplianceCookies.documentReady(function ()
{
	// TODO Check if the user has declined cookies before running this method
	if (true)
	{
		window.AkeebaDataComplianceCookies.disableStorage();
	}

	// TODO If the user has made no preference display the modal or the cookie controls
});