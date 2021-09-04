These view templates are used to render the controls for accepting / rejecting cookies (“Cookie controls”). You can
override them by placing files in templates/YOUR_TEMPLATE/html/plg_system_datacompliancecookie

You need to create a Custom HTML module with the code:
<div id="akeeba-dccc-controls"></div>
The controls for accepting / rejecting cookies will be rendered inside that DIV. The ID can be controlled in the plugin
options.

Alternatively, use the option “Show static controls” (default) to render a permanent overlay at the bottom of your page,
rendered with a floating DIV placed right above the closing </body> tag.