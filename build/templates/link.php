<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Internal linking script
$hardlink_files  = [];
$symlink_files   = [];
$symlink_folders = [
	// Force phpStorm to auto-complete CSS classes
	'../fef/packages/joomla/fef/css/style.min.css' => 'fef.min.css',
];