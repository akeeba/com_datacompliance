<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  \Akeeba\DataCompliance\Admin\View\ControlPanel\Html $this For type hinting in the IDE */

defined('_JEXEC') or die;

use FOF30\Date\Date;

$root = realpath(JPATH_ROOT);
$root = trim($root);
$emptyRoot = empty($root);


?>

<?php /* Stuck database updates warning */?>
<?php if ($this->stuckUpdates):?>
	<div class="akeeba-block--failure">
		<p>
			<?php
			echo \JText::sprintf('COM_DATACOMPLIANCE_CPANEL_ERR_UPDATE_STUCK',
				$this->container->db->getPrefix(),
				'index.php?option=com_datacompliance&view=ControlPanel&task=forceUpdateDb'
			)?>
		</p>
	</div>
<?php endif;?>

<?php
// Obsolete PHP version check
if (version_compare(PHP_VERSION, '5.5.0', 'lt')):
	JLoader::import('joomla.utilities.date');
	$akeebaCommonDatePHP = new Date('2015-09-03 00:00:00', 'GMT');
	$akeebaCommonDateObsolescence = new Date('2016-06-03 00:00:00', 'GMT');
	?>
	<div id="phpVersionCheck" class="akeeba-block--warning">
		<h3><?php echo \JText::_('AKEEBA_COMMON_PHPVERSIONTOOOLD_WARNING_TITLE'); ?></h3>
		<p>
			<?php echo JText::sprintf(
				'AKEEBA_COMMON_PHPVERSIONTOOOLD_WARNING_BODY',
				PHP_VERSION,
				$akeebaCommonDatePHP->format(JText::_('DATE_FORMAT_LC1')),
				$akeebaCommonDateObsolescence->format(JText::_('DATE_FORMAT_LC1')),
				'5.6'
			);
			?>
		</p>
	</div>
<?php endif; ?>