<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\Dispatcher;

use FOF40\Dispatcher\Mixin\ViewAliases;

defined('_JEXEC') or die;

class Dispatcher extends \FOF40\Dispatcher\Dispatcher
{
    use ViewAliases {
        onBeforeDispatch as onBeforeDispatchViewAliases;
    }

    /** @var   string  The name of the default view, in case none is specified */
    public $defaultView = 'Options';

    public function onBeforeDispatch()
    {
        $this->onBeforeDispatchViewAliases();

	    // Load the FOF language
	    $lang = $this->container->platform->getLanguage();
	    $lang->load('lib_fof40', JPATH_ADMINISTRATOR, 'en-GB', true, true);
	    $lang->load('lib_fof40', JPATH_ADMINISTRATOR, null, true, false);

	    // Renderer options (0=none, 1=frontend, 2=backend, 3=both)
	    $useFEF   = in_array($this->container->params->get('load_fef', 3), [1, 3]);
	    $fefReset = $useFEF && in_array($this->container->params->get('fef_reset', 3), [1, 3]);

	    if (!$useFEF)
	    {
		    $this->container->rendererClass = '\\FOF40\\Render\\Joomla3';
	    }

	    $darkMode = $this->container->params->get('dark_mode_frontend', -1);

	    $customCss = 'media://com_datacompliance/css/frontend.css';

	    if ($darkMode != 0)
	    {
		    $customCss .= ', media://com_datacompliance/css/frontend_dark.css';
	    }

	    $this->container->renderer->setOptions([
		    'load_fef'      => $useFEF,
		    'fef_reset'     => $fefReset,
		    'fef_dark'      => $useFEF ? $darkMode : 0,
		    'custom_css'    => $customCss,
		    // Render submenus as drop-down navigation bars powered by Bootstrap
		    'linkbar_style' => 'classic',
	    ]);

	    // Load the version file
        @include_once($this->container->backEndPath . '/version.php');

        if (!defined('DATACOMPLIANCE_VERSION'))
        {
            define('DATACOMPLIANCE_VERSION', 'dev');
            define('DATACOMPLIANCE_DATE', date('Y-m-d'));
        }
    }
}
