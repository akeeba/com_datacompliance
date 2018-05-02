<?php
/**
 * @package   AkeebaLoginGuard
 * @copyright Copyright (c)2016-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Prevent direct access
defined('_JEXEC') or die;

class JFormFieldDatacompliance extends JFormField
{
	/**
	 * Element name
	 *
	 * @var   string
	 */
	protected $_name = 'Datacompliance';

	function getInput()
	{
		$user_id = $this->form->getData()->get('id', null);

		if (is_null($user_id))
		{
			return JText::_('PLG_USER_DATACOMPLIANCE_ERR_NOUSER');
		}

		try
		{
			// Capture the output instead of pushing it to the browser
			@ob_start();

			// Render the other component's view
			FOF30\Container\Container::getInstance('com_datacompliance', array(
				'tempInstance' => true,
				'input' => [
					'view'      => 'Options',
					'returnurl' => base64_encode(JUri::getInstance()->toString()),
					'user_id'   => $user_id
				]
			))->dispatcher->dispatch();

			// Get the output...
			$content = ob_get_contents();

			// ...and close the output buffer
			ob_end_clean();
		}
		catch (\Exception $e)
		{
			// Whoops! The component blew up. Close the output buffer...
			ob_end_clean();
			// ...and indicate that we have no content.
			$content = JText::_('PLG_USER_DATACOMPLIANCE_ERR_NOCOMPONENT');
		}

		if (!class_exists('Akeeba\\DataCompliance\\Site\\View\\Options\\Html'))
		{
			$content = JText::_('PLG_USER_DATACOMPLIANCE_ERR_NOCOMPONENT');
		}

		// Display the content
		return $content;
	}
}
