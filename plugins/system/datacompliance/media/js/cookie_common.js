/**
 * Akeeba Data Compliance
 * A simple tool to manage compliance with the European Union's GDPR
 *
 * Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 *
 * License: GNU General Public License version 3, or later
 */

if (typeof akeeba === "undefined")
{
	var akeeba = {};
}

if (typeof akeeba.DataCompliance == "undefined")
{
	akeeba.DataCompliance = {};
}

/**
 * Removes cookies sent with the page and prevents JavaScript from setting new cookies
 */
akeeba.DataCompliance.killCookies = function ()
{
	// Delete all existing cookies
	document.cookie.split(";").forEach(function (c)
	{
		document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
	});

	// Forbid JavaScript to set cookies. Reference: https://stackoverflow.com/questions/41606070/html-javascript-disable-cookies
	if (!document.__defineGetter__)
	{
		Object.defineProperty(document, "cookie", {
			get   : function ()
			{
				return "";
			}, set: function ()
			{
				return true;
			}
		});
	}
	else
	{
		document.__defineGetter__("cookie", function ()
		{
			return "";
		});
		document.__defineSetter__("cookie", function ()
		{
		});
	}
};

