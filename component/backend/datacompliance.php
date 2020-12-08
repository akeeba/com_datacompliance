<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

define('AKEEBA_COMMON_WRONGPHP', 1);
$minPHPVersion         = '7.1.0';
$recommendedPHPVersion = '7.4';
$softwareName          = 'Akeeba DataCompliance';

if (!require_once(__DIR__ . '/View/wrongphp.php'))
{
	return;
}

// So, FEF is not installed?
if (!@file_exists(JPATH_SITE . '/media/fef/fef.php'))
{
	(include_once __DIR__ . '/View/fef.php') or die('You need to have the Akeeba Frontend Framework (FEF) package installed on your site to display this component. Please visit https://www.akeeba.com/download/official/fef.html to download it and install it on your site.');

	return;
}

try
{
	if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
	{
		throw new RuntimeException('FOF 3.0 is not installed', 500);
	}

	FOF30\Container\Container::getInstance('com_datacompliance')->dispatcher->dispatch();
}
catch (Throwable $e)
{
	$title = 'Akeeba DataCompliance';
	$isPro = false;

	if (!(include_once JPATH_COMPONENT_ADMINISTRATOR . '/View/errorhandler.php'))
	{
		throw $e;
	}
}
