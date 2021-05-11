/*!
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

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
    akeeba.System.doAjax({
        ajaxURL:       "index.php?option=com_datacompliance&view=ControlPanel&task=userstats",
        useTripleHash: false
    }, function (data)
    {
        var ctx     = document.getElementById("adcExpiredUsers").getContext("2d");
        var myChart = new Chart(ctx, {
            type:    "doughnut",
            data:    {
                labels:   ["Inactive", "Active", "Deleted"],
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
    });
};

akeeba.DataCompliance.ControlPanel.loadWipedGraphs = function ()
{
    akeeba.System.doAjax({
        ajaxURL:       "index.php?option=com_datacompliance&view=ControlPanel&task=wipedstats",
        useTripleHash: false
    }, function (data)
    {
        var ctx     = document.getElementById("adcWipedUsers").getContext("2d");
        var myChart = new Chart(ctx, {
            type:    "bar",
            data:    {
                datasets: [
                    {
                        label:           "User",
                        backgroundColor: "#009900",
                        data:            data.user
                    },
                    {
                        label:           "Admin",
                        backgroundColor: "#ff0000",
                        data:            data.admin
                    },
                    {
                        label:           "Lifecycle",
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
    });
};

akeeba.Loader.add(["akeeba.System", "akeeba.Ajax", "Chart"], function ()
{
    akeeba.DataCompliance.ControlPanel.loadUserGraphs();
    akeeba.DataCompliance.ControlPanel.loadWipedGraphs();
});