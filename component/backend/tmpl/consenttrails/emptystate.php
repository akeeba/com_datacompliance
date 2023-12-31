<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

$displayData = [
	'textPrefix' => 'COM_DATACOMPLIANCE_CONSENTTRAILS',
	'formURL'    => 'index.php?option=com_datacompliance&view=consenttrails',
	//'helpURL'    => '',
	'icon'       => 'fa fa-check-square',
	//'createURL'  => '',
];

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
