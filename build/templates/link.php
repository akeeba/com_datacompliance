<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Internal linking script
$hardlink_files  = [];
$symlink_files   = [];
$symlink_folders = [
	// Force phpStorm to auto-complete CSS classes
	'../fef/out/css/fef-joomla.min.css' => 'fef.min.css',
];