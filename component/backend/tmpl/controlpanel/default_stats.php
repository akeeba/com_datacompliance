<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Akeeba\Component\DataCompliance\Administrator\View\Controlpanel\HtmlView $this */
?>

<div class="card mb-3">
	<h3 class="card-header bg-secondary text-white">
		<?= Text::_('COM_DATACOMPLIANCE_CONTROLPANEL_DASHBOARD_INACTIVE') ?>
	</h3>

	<div class="card-body w-100 mx-auto" style="max-width: 350px; max-height: 350px;">
		<canvas id="adcExpiredUsers"></canvas>
	</div>
</div>

<div class="card mb-3">
	<h3 class="card-header bg-secondary text-white">
		<?= Text::_('COM_DATACOMPLIANCE_CONTROLPANEL_DASHBOARD_WIPED') ?>
	</h3>

	<div class="card-body w-100 mx-auto" style="max-width: 350px; max-height: 350px;">
		<canvas id="adcWipedUsers"></canvas>
	</div>
</div>

