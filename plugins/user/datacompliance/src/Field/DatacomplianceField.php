<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\User\DataCompliance\Field;

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;

class DatacomplianceField extends FormField
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
			return Text::_('PLG_USER_DATACOMPLIANCE_ERR_NOUSER');
		}

		/**
		 * Why not use HMVC to display the Options page inside the user form just like we do with LoginGuard? Our page
		 * has a FORM element. This is rendered inside Joomla's own FORM element, since all user fields are form fields,
		 * right? However, Joomla seems to have some magic JavaScript which removes the nested form elements (since
		 * you can't normally have nested form elements), moving their contents one level up. This of course completely
		 * breaks the behaviour of our software. So instead I have to add a link to the actual page. Too tired to battle
		 * with this.
		 */

		$url       = Route::_('index.php?option=com_datacompliance&view=options&user_id=' . $user_id);
		$user      = Factory::getApplication()->getIdentity() ?? new User();
		$isAdmin   = $user_id != $user->id;
		$key       = $isAdmin ? 'PLG_USER_DATACOMPLIANCE_FIELD_INFO_ADMIN' : 'PLG_USER_DATACOMPLIANCE_FIELD_INFO';
		$labelText = Text::_($key);
		$html      = <<< HTML
<a href="$url">$labelText</a>
 
HTML;

		// Display the content
		return $html;
	}
}
