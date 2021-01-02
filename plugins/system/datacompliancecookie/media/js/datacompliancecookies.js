/*!
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
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
        accepted:                false,
        // Has the user already interacted with the prompt (accepting or declining cookies)?
        interacted:              false,
        // The cookie domain and path to use when unsetting cookies
        cookie:                  {
            domain: null,
            path:   null
        },
        // Additional domain names to use when unsetting cookies
        additionalCookieDomains: [],
        // Whitelisted cookie names
        whitelisted:             [],
        ajaxURL:                "",
        token:                   ""
    };

    var construct = function (options)
    {
        Object.assign(me.vars, options);

        me.vars.token = Joomla.getOptions('csrf.token', '');
    };

    /**
     * The user has clicked on one of the buttons in the cookie banner
     *
     * @param   {int}  allowCookies  Are cookies accepted or declined?
     *
     * @returns {boolean}
     */
    this.applyCookiePreference = function (allowCookies)
    {
        var myData            = {
            accepted: allowCookies
        };
        // Pass the Joomla! token
        myData[me.vars.token] = 1;
        me.ajaxCall(this.vars.ajaxURL, {
            method:  "POST",
            timeout: 15000,
            data:    myData,
            success: function (responseText, responseStatus, xhr)
                     {
                         try
                         {
                             var responseObject = JSON.parse(responseText);

                             if (responseObject.success)
                             {
                                 console.info(
                                     "Akeeba DataCompliance Cookies -- AJAX successful: " + responseObject.data);
                             }
                             else
                             {
                                 me.handleAjaxError(responseObject.message);
                             }
                         }
                         catch (e)
                         {
                             console.error(
                                 "Akeeba DataCompliance Cookies -- Cannot parse AJAX response. Assuming success.");
                         }

                         window.location = window.location;
                     },
            error:   function (xhr, errorType, e)
                     {
                         me.handleAjaxError("(" + errorType + ") #" + e.code + " " + e.message);

                         window.location = window.location;
                     }
        });

        // Return false because this is a button element action handler
        return false;
    };

    /**
     * Remove the user's cookie preferences and show the banner again.
     *
     * @returns {boolean}
     */
    this.removeCookiePreference = function ()
    {
        var myData            = {
            reset: 1
        };
        // Pass the Joomla! token
        myData[me.vars.token] = 1;
        me.ajaxCall(this.vars.ajaxURL, {
            method:  "POST",
            timeout: 15000,
            data:    myData,
            success: function (responseText, responseStatus, xhr)
                     {
                         try
                         {
                             var responseObject = JSON.parse(responseText);

                             if (responseObject.success)
                             {
                                 console.info(
                                     "Akeeba DataCompliance Cookies -- Reset AJAX successful: " + responseObject.data);
                             }
                             else
                             {
                                 me.handleAjaxError(responseObject.message);

                                 return;
                             }
                         }
                         catch (e)
                         {
                             console.error(
                                 "Akeeba DataCompliance Cookies -- Cannot parse Reset AJAX response. Assuming success.");
                         }

                         if (me.vars.interacted && me.vars.accepted)
                         {
                             alert(Joomla.Text._("PLG_SYSTEM_DATACOMPLIANCECOOKIE_LBL_REMOVECOOKIES"));
                         }

                         // Hide cookie controls
                         var elAcceptedControls = document.getElementById("akeeba-dccc-controls-accepted");
                         var elDeclinedControls = document.getElementById("akeeba-dccc-controls-declined");

                         if ((elAcceptedControls !== null) && (typeof elAcceptedControls !== "undefined"))
                         {
                             elAcceptedControls.style.display = "none";
                         }

                         if ((elDeclinedControls !== null) && (typeof elDeclinedControls !== "undefined"))
                         {
                             elDeclinedControls.style.display = "none";
                         }

                         // Show banner
                         var elBanner = document.getElementById("akeeba-dccc-banner-container");

                         if ((elBanner !== null) && (typeof elBanner !== "undefined"))
                         {
                             elBanner.style.display = "block";
                         }
                     },
            error:   function (xhr, errorType, e)
                     {
                         me.handleAjaxError("(" + errorType + ") #" + e.code + " " + e.message);

                         window.location = window.location;
                     }
        });

        // Return false because this is a button element action handler
        return false;
    };

    /**
     * Dummy AJAX error handler.
     *
     * This logs all errors to the console. In case of an issue we can instruct the user to give us the console dump so
     * that we can understand what happened.
     *
     * @param   {string}  message
     */
    this.handleAjaxError = function (message)
    {
        console.error("Akeeba DataCompliance Cookies -- AJAX Error: " + message);
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
    this.removeCookie = function (cookieName)
    {
        me.Cookies.remove(cookieName, {path: me.vars.cookie.path, domain: me.vars.cookie.domain});

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

            me.Cookies.remove(cookieName, {path: me.vars.cookie.path, domain: domainName});
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
                return "";
            });

            document.__defineSetter__("cookie", function ()
            {
            });

            return;
        }

        // Legacy browsers (IE 6 and 7)
        if (navigator.appVersion.indexOf("MSIE 6.") === -1 || navigator.appVersion.indexOf("MSIE 7.") === -1)
        {
            Object.defineProperty(document, "cookie", {
                get: function ()
                     {
                         return "";
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
            return decodeURIComponent(str.split("").map(function (c)
            {
                return "%" + ("00" + c.charCodeAt(0).toString(16)).slice(-2)
            }).join(""))
        };

        if (typeof window !== "undefined")
        {
            if (typeof window.atob !== "undefined")
            {
                return decodeUTF8string(window.atob(encodedData))
            }
        }
        else
        {
            return new Buffer(encodedData, "base64").toString("utf-8")
        }

        var b64    = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
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
        var dec    = "";
        var tmpArr = [];

        if (!encodedData)
        {
            return encodedData
        }

        encodedData += "";

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

        dec = tmpArr.join("");

        return decodeUTF8string(dec.replace(/\0+$/, ""));
    };

    /**
     * Performs an asynchronous AJAX request. Mostly compatible with jQuery 1.5+ calling conventions, or at least the
     * subset
     * of the features we used in our software.
     *
     * The parameters can be
     * method        string      HTTP method (GET, POST, PUT, ...). Default: POST.
     * url        string      URL to access over AJAX. Required.
     * timeout    int         Request timeout in msec. Default: 600,000 (ten minutes)
     * data        object      Data to send to the AJAX URL. Default: empty
     * success    function    function(string responseText, string responseStatus, XMLHttpRequest xhr)
     * error        function    function(XMLHttpRequest xhr, string errorType, Exception e)
     * beforeSend    function    function(XMLHttpRequest xhr, object parameters) You can modify xhr, not parameters.
     * Return false to abort the request.
     *
     * @param   url         {string}  URL to send the AJAX request to
     * @param   parameters  {object}  Configuration parameters
     */
    this.ajaxCall = function (url, parameters)
    {
        var xhrSuccessStatus = {
            // File protocol always yields status code 0, assume 200
            0:    200, // Support: IE <=9 only. Sometimes IE returns 1223 when it should be 204
            1223: 204
        };

        /**
         * Converts a simple object containing query string parameters to a single, escaped query string
         *
         * @param    object   {object}  A plain object containing the query parameters to pass
         * @param    prefix   {string}  Prefix for array-type parameters
         *
         * @returns  {string}
         *
         * @access  private
         */
        function interpolateParameters(object, prefix)
        {
            prefix            = prefix || "";
            var encodedString = "";

            for (var prop in object)
            {
                if (object.hasOwnProperty(prop))
                {
                    if (encodedString.length > 0)
                    {
                        encodedString += "&";
                    }

                    if (typeof object[prop] !== "object")
                    {
                        if (prefix === "")
                        {
                            encodedString += encodeURIComponent(prop) + "=" + encodeURIComponent(object[prop]);
                        }
                        else
                        {
                            encodedString +=
                                encodeURIComponent(prefix) + "[" + encodeURIComponent(prop) + "]=" + encodeURIComponent(
                                object[prop]);
                        }

                        continue;
                    }

                    // Objects need special handling
                    encodedString += interpolateParameters(object[prop], prop);
                }
            }
            return encodedString;
        }

        /**
         * Goes through a list of callbacks and calls them in succession. Accepts a variable number of arguments.
         */
        function triggerCallbacks()
        {
            // converts arguments to real array
            var args         = Array.prototype.slice.call(arguments);
            var callbackList = args.shift();

            if (typeof (callbackList) === "function")
            {
                return callbackList.apply(null, args);
            }

            if (callbackList instanceof Array)
            {
                for (var i = 0; i < callbackList.length; i++)
                {
                    var callBack = callbackList[i];

                    if (callBack.apply(null, args) === false)
                    {
                        return false;
                    }
                }
            }

            return null;
        }

        // Handles jQuery 1.0 calling style of .ajax(parameters), passing the URL as a property of the parameters object
        if (typeof (parameters) === "undefined")
        {
            parameters = url;
            url        = parameters.url;
        }

        // Get the parameters I will use throughout
        var method          = (typeof (parameters.type) === "undefined") ? "POST" : parameters.type;
        method              = method.toUpperCase();
        var data            = (typeof (parameters.data) === "undefined") ? {} : parameters.data;
        var sendData        = null;
        var successCallback = (typeof (parameters.success) === "undefined") ? null : parameters.success;
        var errorCallback   = (typeof (parameters.error) === "undefined") ? null : parameters.error;

        // === Cache busting
        var cache = (typeof (parameters.cache) === "undefined") ? false : parameters.url;

        if (!cache)
        {
            var now                = new Date().getTime() / 1000;
            var s                  = parseInt(now, 10);
            data._cacheBustingJunk = Math.round((now - s) * 1000) / 1000;
        }

        // === Interpolate the data
        if ((method === "POST") || (method === "PUT"))
        {
            sendData = interpolateParameters(data);
        }
        else
        {
            url += url.indexOf("?") === -1 ? "?" : "&";
            url += interpolateParameters(data);
        }

        // === Get the XHR object
        var xhr = new XMLHttpRequest();
        xhr.open(method, url);

        // === Handle POST / PUT data
        if ((method === "POST") || (method === "PUT"))
        {
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        }

        // --- Set the load handler
        xhr.onload = function (event)
        {
            var status         = xhrSuccessStatus[xhr.status] || xhr.status;
            var statusText     = xhr.statusText;
            var isBinaryResult = (xhr.responseType || "text") !== "text" || typeof xhr.responseText !== "string";
            var responseText   = isBinaryResult ? xhr.response : xhr.responseText;
            var headers        = xhr.getAllResponseHeaders();

            if (status === 200)
            {
                if (successCallback != null)
                {
                    triggerCallbacks(successCallback, responseText, statusText, xhr);
                }

                return;
            }

            if (errorCallback)
            {
                triggerCallbacks(errorCallback, xhr, "error", null);
            }
        };

        // --- Set the error handler
        xhr.onerror = function (event)
        {
            if (errorCallback)
            {
                triggerCallbacks(errorCallback, xhr, "error", null);
            }
        };

        // IE 8 is a pain the butt
        if (window.attachEvent && !window.addEventListener)
        {
            xhr.onreadystatechange = function ()
            {
                if (this.readyState === 4)
                {
                    var status = xhrSuccessStatus[this.status] || this.status;

                    if (status >= 200 && status < 400)
                    {
                        // Success!
                        xhr.onload();
                    }
                    else
                    {
                        xhr.onerror();
                    }
                }
            };
        }

        // --- Set the timeout handler
        xhr.ontimeout = function ()
        {
            if (errorCallback)
            {
                triggerCallbacks(errorCallback, xhr, "timeout", null);
            }
        };

        // --- Set the abort handler
        xhr.onabort = function ()
        {
            if (errorCallback)
            {
                triggerCallbacks(errorCallback, xhr, "abort", null);
            }
        };

        // --- Apply the timeout before running the request
        var timeout = (typeof (parameters.timeout) === "undefined") ? 600000 : parameters.timeout;

        if (timeout > 0)
        {
            xhr.timeout = timeout;
        }

        // --- Call the beforeSend event handler. If it returns false the request is canceled.
        if (typeof (parameters.beforeSend) !== "undefined")
        {
            if (parameters.beforeSend(xhr, parameters) === false)
            {
                return;
            }
        }

        xhr.send(sendData);
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
;(function (factory)
{
    var registeredInModuleLoader = false;
    if (typeof define === "function" && define.amd)
    {
        define(factory);
        registeredInModuleLoader = true;
    }
    if (typeof exports === "object")
    {
        module.exports           = factory();
        registeredInModuleLoader = true;
    }
    if (!registeredInModuleLoader)
    {
        var OldCookies = window.Cookies;
        var api        = window.Cookies = factory();
        api.noConflict = function ()
        {
            window.Cookies = OldCookies;
            return api;
        };
    }
}(function ()
{
    function extend()
    {
        var i      = 0;
        var result = {};
        for (; i < arguments.length; i++)
        {
            var attributes = arguments[i];
            for (var key in attributes)
            {
                result[key] = attributes[key];
            }
        }
        return result;
    }

    function init(converter)
    {
        function api(key, value, attributes)
        {
            var result;
            if (typeof document === "undefined")
            {
                return;
            }

            // Write

            if (arguments.length > 1)
            {
                attributes = extend({
                    path: "/"
                }, api.defaults, attributes);

                if (typeof attributes.expires === "number")
                {
                    var expires = new Date();
                    expires.setMilliseconds(expires.getMilliseconds() + attributes.expires * 864e+5);
                    attributes.expires = expires;
                }

                // We're using "expires" because "max-age" is not supported by IE
                attributes.expires = attributes.expires ? attributes.expires.toUTCString() : "";

                try
                {
                    result = JSON.stringify(value);
                    if (/^[\{\[]/.test(result))
                    {
                        value = result;
                    }
                }
                catch (e)
                {
                }

                if (!converter.write)
                {
                    value = encodeURIComponent(String(value))
                        .replace(/%(23|24|26|2B|3A|3C|3E|3D|2F|3F|40|5B|5D|5E|60|7B|7D|7C)/g, decodeURIComponent);
                }
                else
                {
                    value = converter.write(value, key);
                }

                key = encodeURIComponent(String(key));
                key = key.replace(/%(23|24|26|2B|5E|60|7C)/g, decodeURIComponent);
                key = key.replace(/[\(\)]/g, escape);

                var stringifiedAttributes = "";

                for (var attributeName in attributes)
                {
                    if (!attributes[attributeName])
                    {
                        continue;
                    }
                    stringifiedAttributes += "; " + attributeName;
                    if (attributes[attributeName] === true)
                    {
                        continue;
                    }
                    stringifiedAttributes += "=" + attributes[attributeName];
                }
                return (document.cookie = key + "=" + value + stringifiedAttributes);
            }

            // Read

            if (!key)
            {
                result = {};
            }

            // To prevent the for loop in the first place assign an empty array
            // in case there are no cookies at all. Also prevents odd result when
            // calling "get()"
            var cookies = document.cookie ? document.cookie.split("; ") : [];
            var rdecode = /(%[0-9A-Z]{2})+/g;
            var i       = 0;

            for (; i < cookies.length; i++)
            {
                var parts  = cookies[i].split("=");
                var cookie = parts.slice(1).join("=");

                if (!this.json && cookie.charAt(0) === "\"")
                {
                    cookie = cookie.slice(1, -1);
                }

                try
                {
                    var name = parts[0].replace(rdecode, decodeURIComponent);
                    cookie   = converter.read ?
                               converter.read(cookie, name) : converter(cookie, name) ||
                                   cookie.replace(rdecode, decodeURIComponent);

                    if (this.json)
                    {
                        try
                        {
                            cookie = JSON.parse(cookie);
                        }
                        catch (e)
                        {
                        }
                    }

                    if (key === name)
                    {
                        result = cookie;
                        break;
                    }

                    if (!key)
                    {
                        result[name] = cookie;
                    }
                }
                catch (e)
                {
                }
            }

            return result;
        }

        api.set      = api;
        api.get      = function (key)
        {
            return api.call(api, key);
        };
        api.getJSON  = function ()
        {
            return api.apply({
                json: true
            }, [].slice.call(arguments));
        };
        api.defaults = {};

        api.remove = function (key, attributes)
        {
            api(key, "", extend(attributes, {
                expires: -1
            }));
        };

        api.withConverter = init;

        return api;
    }

    return init(function ()
    {
    });
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
if (typeof Object.assign != "function")
{
    // Must be writable: true, enumerable: false, configurable: true
    Object.defineProperty(Object, "assign", {
        value:        function assign(target, varArgs)
                      { // .length of function is 2
                          "use strict";
                          if (target == null)
                          { // TypeError if undefined or null
                              throw new TypeError("Cannot convert undefined or null to object");
                          }

                          var to = Object(target);

                          for (var index = 1; index < arguments.length; index++)
                          {
                              var nextSource = arguments[index];

                              if (nextSource != null)
                              { // Skip over if undefined or null
                                  for (var nextKey in nextSource)
                                  {
                                      // Avoid bugs when hasOwnProperty is shadowed
                                      if (Object.prototype.hasOwnProperty.call(nextSource, nextKey))
                                      {
                                          to[nextKey] = nextSource[nextKey];
                                      }
                                  }
                              }
                          }
                          return to;
                      },
        writable:     true,
        configurable: true
    });
}

window.AkeebaDataComplianceCookies = new AkeebaDataComplianceCookies(Joomla.getOptions("com_datacompliance"));
AkeebaDataComplianceCookiesOnDocumentReady("documentReady", window.AkeebaDataComplianceCookies);
window.AkeebaDataComplianceCookies.Cookies = Cookies.noConflict();

// Set up the document's ready event handler
AkeebaDataComplianceCookies.documentReady(function ()
{
    // If the user has not accepted cookies for this site we should block them
    if (!window.AkeebaDataComplianceCookies.vars.accepted)
    {
        window.AkeebaDataComplianceCookies.disableStorage();
    }

    /**
     * If the user has made no preference the cookie banner HTML ('banner' view template) is included and displayed
     * automatically. We do not need to do anything further so we can return early from this code.
     *
     * If, however the user has already interacted with the banner then we have loaded the 'controls' view template
     * which includes two different HTML blocks, depending on whether cookies were accepted or rejected. We need to hide
     * both, locate the controls container and then put the correct HTML block inside it and show it.
     */
    if (!window.AkeebaDataComplianceCookies.vars.interacted)
    {
        return;
    }

    // Hide the banner
    var elBanner = document.getElementById("akeeba-dccc-banner-container");

    if ((elBanner !== null) && (typeof elBanner !== "undefined"))
    {
        elBanner.style.display = "none";
    }

    // Hide both controls (akeeba-dccc-controls-accepted and akeeba-dccc-controls-declined)
    var elAcceptedControls = document.getElementById("akeeba-dccc-controls-accepted");
    var elDeclinedControls = document.getElementById("akeeba-dccc-controls-declined");

    if ((elAcceptedControls !== null) && (typeof elAcceptedControls !== "undefined"))
    {
        elAcceptedControls.style.display = "none";
    }

    if ((elDeclinedControls !== null) && (typeof elDeclinedControls !== "undefined"))
    {
        elDeclinedControls.style.display = "none";
    }

    // Find the control holder (akeeba-dccc-controls). If it's not found, exit immediately.
    var elHolder = document.getElementById("akeeba-dccc-controls");

    if ((elHolder === null) || (typeof elHolder === "undefined"))
    {
        return;
    }

    if (window.AkeebaDataComplianceCookies.vars.accepted)
    {
        if ((elAcceptedControls !== null) && (typeof elAcceptedControls !== "undefined"))
        {
            // Show akeeba-dccc-controls-accepted
            elAcceptedControls.style.display = "block";

            // Move akeeba-dccc-controls-accepted into akeeba-dccc-controls
            elHolder.appendChild(elAcceptedControls);
        }

        return;
    }

    if ((elDeclinedControls !== null) && (typeof elDeclinedControls !== "undefined"))
    {
        // Show akeeba-dccc-controls-declined
        elDeclinedControls.style.display = "block";

        // Move akeeba-dccc-controls-declined into akeeba-dccc-controls
        elHolder.appendChild(elDeclinedControls);
    }
});