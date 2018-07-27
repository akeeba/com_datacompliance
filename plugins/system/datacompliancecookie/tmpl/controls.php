<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  \Joomla\CMS\Plugin\CMSPlugin  $this */
?>
<div id="akeeba-dccc-controls-accepted">
	<p id="akeeba-dccc-controls-accepted-text" class="akeeba-dccc-controls-text">
		<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_ACCEPT_TEXT') ?>
	</p>
	<div id="akeeba-dccc-controls-accepted-buttons" class="akeeba-dccc-controls-buttons">
		<button onclick="return false;" class="akeeba-btn--red">
			<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_BTN_REVOKE') ?>
		</button>
	</div>
</div>

<div id="akeeba-dccc-controls-declined">
	<p id="akeeba-dccc-controls-declined-text" class="akeeba-dccc-controls-text">
		<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_DECLINE_TEXT') ?>
	</p>
	<div id="akeeba-dccc-controls-declined-buttons" class="akeeba-dccc-controls-buttons">
		<button onclick="return false;" class="akeeba-btn--red">
			<?php echo JText::_('PLG_SYSTEM_DATACOMPLIANCECOOKIE_CONTROLS_BTN_RECONSIDER') ?>
		</button>
	</div>
</div>
