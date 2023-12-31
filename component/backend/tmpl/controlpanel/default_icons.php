<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Akeeba\Component\DataCompliance\Administrator\View\Controlpanel\HtmlView $this */

?>
<div class="card mb-3">
	<h3 class="card-header bg-primary text-white">
		<?= Text::_('COM_DATACOMPLIANCE_CONTROLPANEL_HEADER_AUDIT'); ?>
	</h3>

	<div class="card-body d-flex flex-row flex-wrap align-items-stretch">
		<a class="text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em"
		   href="<?= Route::_('index.php?option=com_datacompliance&view=Consenttrails') ?>">
			<div class="bg-primary text-white d-block text-center p-3 h2">
				<span class="fa fa-check-square"></span>
			</div>
			<span>
				<?= Text::_('COM_DATACOMPLIANCE_CONSENTTRAILS') ?>
			</span>
		</a>

		<a class="text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em"
		   href="<?= Route::_('index.php?option=com_datacompliance&view=Usertrails') ?>">
			<div class="bg-primary text-white d-block text-center p-3 h2">
				<span class="fa fa-users"></span>
			</div>
			<span>
				<?= Text::_('COM_DATACOMPLIANCE_USERTRAILS') ?>
			</span>
		</a>

		<a class="text-center align-self-stretch btn btn-outline-warning text-dark border-0" style="width: 10em"
		   href="<?= Route::_('index.php?option=com_datacompliance&view=Exporttrails') ?>">
			<div class="bg-warning d-block text-center p-3 h2">
				<span class="fa fa-file-export"></span>
			</div>
			<span>
				<?= Text::_('COM_DATACOMPLIANCE_EXPORTTRAILS') ?>
			</span>
		</a>

		<a class="text-center align-self-stretch btn btn-outline-danger border-0" style="width: 10em"
		   href="<?= Route::_('index.php?option=com_datacompliance&view=Wipetrails') ?>">
			<div class="bg-danger text-white d-block text-center p-3 h2">
				<span class="fa fa-user-minus"></span>
			</div>
			<span>
					<?= Text::_('COM_DATACOMPLIANCE_WIPETRAILS') ?>
				</span>
		</a>
	</div>
</div>

<div class="card mb-3">
	<h3 class="card-header bg-info text-white">
		<?= Text::_('COM_DATACOMPLIANCE_CONTROLPANEL_HEADER_ACTIONS'); ?>
	</h3>

	<div class="card-body d-flex flex-row flex-wrap align-items-stretch">
		<a class="text-center align-self-stretch btn btn-outline-dark border-0" style="width: 10em"
		   href="<?= Route::_('index.php?option=com_datacompliance&view=Lifecycle') ?>">
			<div class="bg-dark text-white d-block text-center p-3 h2">
				<span class="fa fa-user-clock"></span>
			</div>
			<span>
				<?= Text::_('COM_DATACOMPLIANCE_LIFECYCLE') ?>
			</span>
		</a>
	</div>
</div>

<div class="card mb-3">
	<h3 class="card-header bg-dark text-white">
		<?= Text::_('COM_DATACOMPLIANCE_CONTROLPANEL_HEADER_EMAIL'); ?>
	</h3>

	<div class="card-body d-flex flex-row flex-wrap align-items-stretch">
		<a class="text-center align-self-stretch btn btn-outline-dark border-0" style="width: 10em"
		   href="<?= Route::_('index.php?option=com_datacompliance&view=Emailtemplates') ?>">
			<div class="bg-dark text-white d-block text-center p-3 h2">
				<span class="fa fa-envelope-open-text"></span>
			</div>
			<span>
				<?= Text::_('COM_DATACOMPLIANCE_EMAILTEMPLATES_BTN_MAILTEMPLATES') ?>
			</span>
		</a>
	</div>
</div>