<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  \Akeeba\DataCompliance\Admin\View\ControlPanel\Html $this For type hinting in the IDE */

// Protect from unauthorized access
defined('_JEXEC') or die;

?>
<div class="akeeba-panel--default">
    <header class="akeeba-block-header">
        <h3><?php echo \JText::_('COM_DATACOMPLIANCE_CONTROLPANEL_DASHBOARD_INACTIVE'); ?></h3>
    </header>

    <div style="width: 100%; max-width: 350px; max-height: 350px; margin-left: auto; margin-right: auto">
        <canvas id="adcExpiredUsers"></canvas>
    </div>
</div>

<div class="akeeba-panel--default">
    <header class="akeeba-block-header">
        <h3><?php echo \JText::_('COM_DATACOMPLIANCE_CONTROLPANEL_DASHBOARD_WIPED'); ?></h3>
    </header>

    <div style="width: 100%;">
        <canvas id="adcWipedUsers"></canvas>
    </div>
</div>
