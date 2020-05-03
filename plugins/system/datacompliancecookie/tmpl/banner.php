<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  PlgSystemDatacompliancecookie  $this */

// Prevent direct access
defined('_JEXEC') or die;

$position = $this->params->get('bannerPosition', 'top');
$style = $this->hasCookiePreference && $this->hasAcceptedCookies ? 'style="display: none"' : '';

if ($this->hasCookiePreference)
{
	$style = 'style="display: none"';
}
?>
<div id="akeeba-dccc-banner-container" class="akeeba-renderer-fef akeeba-dccc-banner-<?= $position ?>" <?=$style?>>
	<div class="akeeba-panel--primary">
		<header class="akeeba-block-header">
			<h2 class="akeeba-dccc-banner-header">
				<span class="akion-alert-circled"></span>
				<?= JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_HEADER') ?>
			</h2>
		</header>

		<p class="akeeba-dccc-banner-text">
			<?=  JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_TEXT') ?>
		</p>

		<div id="akeeba-dccc-banner-controls">
			<button onclick="window.AkeebaDataComplianceCookies.applyCookiePreference(0); return false;" class="akeeba-btn--red">
				<span class="akion-ios-close-outline"></span>
				<?= JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_BTN_DECLINE') ?>
			</button>
			<button onclick="window.AkeebaDataComplianceCookies.applyCookiePreference(1); return false;" class="akeeba-btn--green">
				<span class="akion-ios-checkmark-outline"></span>
				<?= JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_BTN_AGREE') ?>
			</button>
		</div>

		<ul class="akeeba-dccc-banner-links">
			<li>
				<a href="<?= $this->params->get('privacyPolicyURL', '') ?>"
				   class="akeeba-dccc-banner-link-privacypolicy">
					<?= JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_LBL_PRIVACYPOLICY') ?>
				</a>
			</li>
			<li>
				<a href="https://eur-lex.europa.eu/LexUriServ/LexUriServ.do?uri=CELEX:32002L0058:EN:NOT"
					class="akeeba-dccc-banner-link-privacypolicy-eprivacy" target="_blank">
					<?= JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_LBL_EPRIVACY') ?>
				</a>
			</li>
			<li>
				<a href="https://eur-lex.europa.eu/legal-content/EN/TXT/HTML/?uri=CELEX:32016R0679"
					class="akeeba-dccc-banner-link-gdpr" target="_blank">
					<?= JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_LBL_GDPR') ?>
				</a>
			</li>
		</ul>
	</div>
</div>
