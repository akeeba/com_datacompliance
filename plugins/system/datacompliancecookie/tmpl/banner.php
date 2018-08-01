<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  \Joomla\CMS\Plugin\CMSPlugin  $this */

$position = $this->params->get('bannerPosition', 'top');
?>
<div id="akeeba-dccc-banner-container" class="akeeba-renderer-fef akeeba-dccc-banner-<?php echo $position ?>">
	<div class="akeeba-panel--primary">
		<header class="akeeba-block-header">
			<h2 class="akeeba-dccc-banner-header">
				<span class="akion-alert-circled"></span>
				<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_HEADER') ?>
			</h2>
		</header>

		<p class="akeeba-dccc-banner-text">
			<?php echo  JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_TEXT') ?>
		</p>

		<div id="akeeba-dccc-banner-controls">
			<button onclick="window.AkeebaDataComplianceCookies.applyCookiePreference(0); return false;" class="akeeba-btn--red">
				<span class="akion-ios-close-outline"></span>
				<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_BTN_DECLINE') ?>
			</button>
			<button onclick="window.AkeebaDataComplianceCookies.applyCookiePreference(1); return false;" class="akeeba-btn--green">
				<span class="akion-ios-checkmark-outline"></span>
				<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_BTN_AGREE') ?>
			</button>
		</div>

		<ul class="akeeba-dccc-banner-links">
			<li>
				<a href="<?php echo $this->params->get('privacyPolicyURL', '') ?>"
				   class="akeeba-dccc-banner-link-privacypolicy">
					<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_LBL_PRIVACYPOLICY') ?>
				</a>
			</li>
			<li>
				<a href="https://eur-lex.europa.eu/LexUriServ/LexUriServ.do?uri=CELEX:32002L0058:EN:NOT"
					class="akeeba-dccc-banner-link-privacypolicy-eprivacy" target="_blank">
					<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_LBL_EPRIVACY') ?>
				</a>
			</li>
			<li>
				<a href="https://eur-lex.europa.eu/legal-content/EN/TXT/HTML/?uri=CELEX:32016R0679"
					class="akeeba-dccc-banner-link-gdpr" target="_blank">
					<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_BANNER_LBL_GDPR') ?>
				</a>
			</li>
		</ul>
	</div>
</div>
