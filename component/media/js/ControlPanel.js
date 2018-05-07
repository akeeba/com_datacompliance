/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof(akeeba) === 'undefined')
{
	var akeeba = {};
}

if (typeof(akeeba.DataCompliance) === 'undefined')
{
	akeeba.DataCompliance = {};
}

akeeba.DataCompliance.ControlPanel = {};

akeeba.DataCompliance.ControlPanel.loadUserGraphs = function()
{
	let url = 'index.php?option=com_datacompliance&view=ControlPanel&task=userstats';

	window.jQuery.getJSON(url, function(data)
	{
		let ctx = document.getElementById("adcExpiredUsers").getContext('2d');
		let myChart = new Chart(ctx, {
			type: 'pie',
			data: {
				labels: ["Inactive", "Active", "Deleted"],
				datasets: [{
					data: [
						data.expired, data.active, data.deleted
					],
					backgroundColor: [
						'#ff0000',
						'#009900',
						'#666666'
					],
					borderWidth: 6
				}]
			},
			options: {
				cutoutPercentage: 50,
				legend: {
					display: false
				}
			}
		});
	});
};