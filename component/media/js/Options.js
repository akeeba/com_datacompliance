/*!
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

akeeba.Loader.add(["akeeba.System"], function ()
{
    akeeba.System.iterateNodes("a.akeebaDataComplianceArticleToggle", function (element)
    {
        akeeba.System.addEventListener(element, "click", function (event)
        {
            event.preventDefault();

            var elTarget = event.currentTarget;

            if (elTarget.style.display === "none")
            {
                elTarget.style.display = "block";

                return false;
            }

            elTarget.style.display = "none";

            return false;
        })
    });
});