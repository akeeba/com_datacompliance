/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
"use strict";

document.querySelectorAll('a.akeebaDataComplianceArticleToggle').forEach(function(element) {
   element.addEventListener('click', function(event) {
       event.preventDefault();

       const elTarget = document.getElementById('datacompliance-article');

       if (elTarget.style.display === "none")
       {
           elTarget.style.display = "block";

           return false;
       }

       elTarget.style.display = "none";

       return false;
   })
});