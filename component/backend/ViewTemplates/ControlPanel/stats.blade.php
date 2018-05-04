<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  \Akeeba\DataCompliance\Admin\View\ControlPanel\Html $this For type hinting in the IDE */

// Protect from unauthorized access
defined('_JEXEC') or die;

?>
{{--- TODO Graphs ---}}
<div class="akeeba-panel--default">
    <header class="akeeba-block-header">
        <h3><?php echo \JText::_('COM_DATACOMPLIANCE_CONTROLPANEL_DASHBOARD_INACTIVE'); ?></h3>
    </header>

    <div style="width: 100%; max-width: 350px; max-height: 350px; margin-left: auto; margin-right: auto">
        <canvas id="adcExpiredUsers"></canvas>
    </div>
</div>

<script>
	var ctx = document.getElementById("adcExpiredUsers").getContext('2d');
	var myChart = new Chart(ctx, {
		type: 'pie',
		data: {
			labels: ["Inactive", "Active"],
			datasets: [{
				data: [12000, 8000],
				backgroundColor: [
					'#ff0000',
					'#009900',
				],
				borderWidth: 4
			}]
		},
		options: {
			cutoutPercentage: 50,
			legend: {
				display: false
			}
		}
	});
</script>