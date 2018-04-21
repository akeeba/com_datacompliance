/**
 * Akeeba Data Compliance
 * A simple tool to manage compliance with the European Union's GDPR
 *
 * Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 *
 * License: GNU General Public License version 3, or later
 *
 * This file can be overridden by copying media/plg_system_datacompliance/js/cookie_init.js to
 * templates/YOURTEMPLATE/js/plg_system_datacompliance/cookie_init.js and editing the new file. Refer to
 * https://cookieconsent.insites.com/documentation/javascript-api/ for all the available options
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
 * See https://cookieconsent.insites.com/documentation/javascript-api/
 */
akeeba.DataCompliance.cookieConsentOptions = {
	type: 'opt-in',
	position: 'top',
	revokable: true,
	onInitialise: function (status) {
		var type = this.options.type;
		var didConsent = this.hasConsented();

		if (type == 'opt-out' && !didConsent) {
			// Disable cookies
			akeeba.DataCompliance.disableCookies();
		}
	},

	onStatusChange: function(status, chosenBefore) {
		var type = this.options.type;
		var didConsent = this.hasConsented();

		if (type == 'opt-in' && didConsent) {
			akeeba.DataCompliance.enableCookies();
		}

		if (type == 'opt-out' && !didConsent) {
			akeeba.DataCompliance.disableCookies();
		}
	},

	onRevokeChoice: function() {
		var type = this.options.type;
		if (type == 'opt-in') {
			akeeba.DataCompliance.disableCookies();
		}

		if (type == 'opt-out') {
			akeeba.DataCompliance.enableCookies();
		}
	}
};