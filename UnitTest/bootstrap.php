<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Required for classes which guard against direct web access.
define('_JEXEC', 1);

/**
 * The Joomla version the version-limit tests compare against. No Joomla is loaded: this only stands in
 * for the constant VersionLimits reads.
 */
define('JVERSION', '6.1.3');

// The component's Composer autoloader (akeeba/s3), plus the prefixes of the code under test.
/** @var \Composer\Autoload\ClassLoader $autoload */
$autoload = require_once __DIR__ . '/../component/backend/vendor/autoload.php';
$autoload->addPsr4('Akeeba\\Component\\DataCompliance\\Administrator\\', __DIR__ . '/../component/backend/src');
$autoload->addPsr4('Akeeba\\Component\\DataCompliance\\Site\\', __DIR__ . '/../component/frontend/src');
$autoload->addPsr4('Akeeba\\DataCompliance\\UnitTest\\', __DIR__);

/**
 * Stand-ins for the three plain value classes of Joomla's com_privacy export API, which
 * Helper\Export::mapJoomlaPrivacyExportDomain() consumes. They mirror core's public API exactly
 * (public $name/$description/$id/$value, addItem/getItems, addField/getFields) and nothing else.
 */
require_once __DIR__ . '/Stubs/PrivacyExport.php';

// Enable verbose error and notices
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set the timezone to UTC to avoid surprises.
@date_default_timezone_set('UTC');
