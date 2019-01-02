<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

$id          = isset($id) ? $id : $field;
$readonly    = isset($readonly) ? ($readonly ? true : false) : false;
$placeholder = isset($placeholder) ? JText::_($placeholder) : JText::_('JLIB_FORM_SELECT_USER');
$userID      = $item->getFieldValue($field, 0);
$user        = $item->getContainer()->platform->getUser($userID);
$width       = isset($width) ? $width : 800;
$height      = isset($height) ? $height : 500;

$uri = new JUri('index.php?option=com_users&view=users&layout=modal&tmpl=component');
$uri->setVar('required', (isset($required) ? ($required ? 1 : 0) : 0));
$uri->setVar('field', $field);
$url = 'index.php' . $uri->toString(['query']);
?>
@unless($readonly)
@jhtml('behavior.modal', 'a.userSelectModal_' . $this->escape($field))
@jhtml('script', 'jui/fielduser.min.js', ['version' => 'auto', 'relative' => true])
@endunless

<div class="akeeba-input-group">
    <input readonly type="text"
           id="{{{ $field }}}" value="{{{ $user->username }}}"
           placeholder="{{{ $placeholder }}}"/>
    <span class="akeeba-input-group-btn">
		<a href="@route($url)"
           class="akeeba-btn--grey userSelectModal_{{{ $field }}}" title="{{{ $placeholder }}}"
           rel="{handler: 'iframe', size: {x: {{$width}}, y: {{$height}} }}">
			<span class="akion-person"></span>
		</a>
	</span>
</div>
@unless($readonly)
<input type="hidden" id="{{{ $field }}}_id" name="{{{ $field }}}" value="{{ (int) $userID }}"/>
@endunless
