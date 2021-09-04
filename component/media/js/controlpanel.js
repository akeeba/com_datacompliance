/*
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
"use strict";

if (typeof (akeeba) === "undefined")
{
    var akeeba = {};
}

if (typeof (akeeba.DataCompliance) === "undefined")
{
    akeeba.DataCompliance = {};
}

akeeba.DataCompliance.ControlPanel = {};

akeeba.DataCompliance.ControlPanel.loadUserGraphs = function ()
{
    const ajaxUrl = Joomla.getOptions("com_datacompliance.controlpanel.userGraphsUrl");

    Joomla.request({
        url:       ajaxUrl,
        method:    "GET",
        perform:   true,
        onSuccess: rawJson =>
                   {
                       const data    = JSON.parse(rawJson);
                       const ctx     = document.getElementById("adcExpiredUsers").getContext("2d");

                       new Chart(ctx, {
                           type:    "doughnut",
                           data:    {
                               labels:   [
                                   Joomla.Text._("COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_INACTIVE"),
                                   Joomla.Text._("COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_ACTIVE"),
                                   Joomla.Text._("COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_DELETED"),
                               ],
                               datasets: [
                                   {
                                       data:            [
                                           data.expired, data.active, data.deleted
                                       ],
                                       backgroundColor: [
                                           "#ff0000",
                                           "#009900",
                                           "#666666"
                                       ],
                                       borderWidth:     6
                                   }
                               ]
                           },
                           options: {
                               cutout:  "50%",
                               plugins: {
                                   legend: {
                                       display: false
                                   }
                               }
                           }
                       });
                   }
    });
};

akeeba.DataCompliance.ControlPanel.loadWipedGraphs = function ()
{
    const ajaxUrl = Joomla.getOptions("com_datacompliance.controlpanel.wipedGraphsUrl");

    Joomla.request({
        url:       ajaxUrl,
        method:    "GET",
        perform:   true,
        onSuccess: rawJson =>
                   {
                       const data  = JSON.parse(rawJson);
                       const ctx     = document.getElementById("adcWipedUsers").getContext("2d");

                       new Chart(ctx, {
                           type:    "bar",
                           data:    {
                               datasets: [
                                   {
                                       label:           Joomla.Text._('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_USER'),
                                       backgroundColor: "#009900",
                                       data:            data.user
                                   },
                                   {
                                       label:           Joomla.Text._('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_ADMIN'),
                                       backgroundColor: "#ff0000",
                                       data:            data.admin
                                   },
                                   {
                                       label:           Joomla.Text._('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_LIFECYCLE'),
                                       backgroundColor: "#666666",
                                       data:            data.lifecycle
                                   },
                               ],
                           },
                           options: {
                               scales:  {
                                   x: {
                                       stacked:      true,
                                       type:         "time",
                                       time:         {
                                           unit: "day"
                                       },
                                       distribution: "linear"
                                   }
                                   ,
                                   y: {
                                       type:    "linear",
                                       stacked: true,
                                       ticks:   {
                                           callback: function (value, index, values)
                                                     {
                                                         return "" + value;
                                                     }
                                       }
                                   }
                               },
                               plugins: {
                                   legend: {
                                       display:  true,
                                       position: "right"
                                   }
                               }
                           }
                       });
                   }
    });
};

akeeba.DataCompliance.ControlPanel.loadUserGraphs();
akeeba.DataCompliance.ControlPanel.loadWipedGraphs();