<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Dispatcher;

defined('_JEXEC') or die;

use FOF30\Dispatcher\Mixin\ViewAliases;

class Dispatcher extends \FOF30\Dispatcher\Dispatcher
{
    use ViewAliases {
        onBeforeDispatch as onBeforeDispatchViewAliases;
    }

    /** @var   string  The name of the default view, in case none is specified */
    public $defaultView = 'ControlPanel';

    public function onBeforeDispatch()
    {
        $this->onBeforeDispatchViewAliases();

	    // Load the FOF language
	    $lang = $this->container->platform->getLanguage();
	    $lang->load('lib_fof30', JPATH_ADMINISTRATOR, 'en-GB', true, true);
	    $lang->load('lib_fof30', JPATH_ADMINISTRATOR, null, true, false);

	    // Renderer options (0=none, 1=frontend, 2=backend, 3=both)
	    $useFEF   = $this->container->params->get('load_fef', 3);
	    $fefReset = $this->container->params->get('fef_reset', 3);

	    // FEF Renderer options. Used to load the common CSS file.
		$this->container->renderer->setOptions([
			// Classic linkbar for drop-down menu display
			'linkbar_style' => 'classic',
			// Load custom CSS file, comma separated list
			'custom_css'    => 'media://com_datacompliance/css/backend.css',
			'load_fef'      => in_array( $useFEF, [ 2, 3 ] ),
			'fef_reset'     => in_array( $fefReset, [ 2, 3 ] )
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
