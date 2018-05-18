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


akeeba.DataCompliance.ControlPanel.loadWipedGraphs = function()
{
	let url = 'index.php?option=com_datacompliance&view=ControlPanel&task=wipedstats';

	window.jQuery.getJSON(url, function(data)
	{
		let ctx = document.getElementById("adcWipedUsers").getContext('2d');
		let myChart = new Chart(ctx, {
			type: 'bar',
			data: {
				datasets: [
					{
						label: 'User',
						backgroundColor: '#009900',
						data: data.user
					},
					{
						label: 'Admin',
						backgroundColor: '#ff0000',
						data: data.admin
					},
					{
						label: 'Lifecycle',
						backgroundColor: '#666666',
						data: data.lifecycle
					},
				],
			},
			options: {
				scales: {
					xAxes: [{
						stacked: true,
						type: 'time',
						time: {
							unit: 'day'
						},
						distribution: 'linear'
					}],
					yAxes: [{
						type: 'linear',
						stacked: true,
						ticks: {
							callback: function(value, index, values) {
								return '' + value;
							}
						}
					}]
				},
				legend: {
					display: true,
					position: 'right'
				}
			}
		});
	});
};