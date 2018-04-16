<?php
/**
 * @package   Akeeba Connection
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Connection\Admin\Dispatcher;

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

		// FEF Renderer options. Used to load the common CSS file.
		$this->container->renderer->setOptions([
			'custom_css' => 'admin://components/com_connection/media/css/backend.min.css'
		]);

	    // Load the version file
        @include_once($this->container->backEndPath . '/version.php');

        if (!defined('AKCONNECTION_VERSION'))
        {
            define('AKCONNECTION_VERSION', 'dev');
            define('AKCONNECTION_DATE', date('Y-m-d'));
        }

        // Inject JS code to namespace the current jQuery instance
        if($this->container->platform->getDocument()->getType() == 'html')
        {
            \JHtml::_('jquery.framework');
            $this->container->template->addJS('admin://components/com_connection/media/js/namespace.min.js', false, false, AKCONNECTION_VERSION);
        }
    }
}
