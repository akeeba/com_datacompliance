<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  plgSystemDataCompliancecookie  $this */

// Prevent direct access
defined('_JEXEC') or die;

$dntCompliance = $this->params->get('dntCompliance', 'ignore');

?>
<div id="akeeba-dccc-controls-accepted" class="akeeba-renderer-fef" style="display: none">
	<div class="akeeba-panel--info">
		<?php if (($dntCompliance == 'overridepreference') && ($this->getDnt() === 0)):
		// The user's preference is overridden by their browser's Do Not Track settings
		?>
		<p>
			<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_DNTOVERRIDE_ACCEPT_TEXT') ?>
		</p>
		<div id="akeeba-dccc-controls-accepted-buttons" class="akeeba-dccc-controls-buttons">
			<button data-cookie-preference="0"
					class="akeebaDataComplianceCookiePreference akeeba-btn--red">
				<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_BTN_DECLINECOOKIES') ?>
			</button>
		</div>
		<?php else:
		// Do Not Track (DNT) browser setting is overridden by the user's preference, DNT is not set or DNT is ignored
		?>
		<p id="akeeba-dccc-controls-accepted-text" class="akeeba-dccc-controls-text">
			<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_ACCEPT_TEXT') ?>
		</p>
		<div id="akeeba-dccc-controls-accepted-buttons" class="akeeba-dccc-controls-buttons">
			<button data-cookie-preference="-1"
					class="akeebaDataComplianceCookiePreference akeeba-btn--red">
				<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_BTN_REVOKE') ?>
			</button>
		</div>
		<?php endif; ?>
	</div>
</div>

<div id="akeeba-dccc-controls-declined" class="akeeba-renderer-fef" style="display: none">
	<div class="akeeba-panel--orange">
		<?php if (($dntCompliance == 'overridepreference') && ($this->getDnt() === 1)):
		// The user's preference is overridden by their browser's Do Not Track settings
		?>
		<p>
			<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_DNTOVERRIDE_DECLINE_TEXT') ?>
		</p>
		<div id="akeeba-dccc-controls-accepted-buttons" class="akeeba-dccc-controls-buttons">
			<button data-cookie-preference="1"
					class="akeebaDataComplianceCookiePreference akeeba-btn--green">
				<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_BTN_ACCEPTCOOKIES') ?>
			</button>
		</div>
		<?php else:
		// Do Not Track (DNT) browser setting is overridden by the user's preference, DNT is not set or DNT is ignored
		?>
		<p id="akeeba-dccc-controls-declined-text" class="akeeba-dccc-controls-text">
			<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_DECLINE_TEXT') ?>
		</p>
		<div id="akeeba-dccc-controls-declined-buttons" class="akeeba-dccc-controls-buttons">
			<button data-cookie-preference="-1"
					class="akeebaDataComplianceCookiePreference akeeba-btn--green">
				<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_BTN_RECONSIDER') ?>
			</button>
		</div>
		<?php endif; ?>
	</div>
</div>
