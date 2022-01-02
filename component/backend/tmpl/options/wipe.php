<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$otherInfo = Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_OTHERINFO');
$otherInfo = ($otherInfo == 'COM_DATACOMPLIANCE_OPTIONS_WIPE_OTHERINFO') ? '' : $otherInfo;

?>
<div class="card mb-3 border-danger">
	<h2 class="card-header h1 bg-danger text-white">
		<?php if($this->type == 'user'): ?>
		<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_HEADER') ?>
		<?php else: ?>
		<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ADMIN_HEADER', $this->user->username) ?>
		<?php endif ?>
	</h2>
	<div class="card-body">
		<div class="alert alert-warning">
			<h3 class="h1">
				<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_1') ?>
			</h3>
			<h4 class="h2">
				<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_2') ?>
			</h4>
			<?php if($this->type == 'user'): ?>
				<h5 class="h3">
					<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_3') ?>
				</h5>
			<?php else: ?>
				<h5 class="h3">
					<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_SCARY_3_ADMIN') ?>
				</h5>
			<?php endif ?>
		</div>

		<p>
			<?php if($this->type == 'user'): ?>
				<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_ACTIONSTOBETAKEN') ?>
			<?php else: ?>
				<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ACTIONSTOBETAKEN_ADMIN', $this->user->username) ?>
			<?php endif ?>
		</p>
		<ul>
			<?php foreach ($this->bulletPoints as $bullet): ?>
				<li><?= $bullet ?></li>
			<?php endforeach ?>
		</ul>
		<p>
			<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_AUDITTRAILNOTICE') ?>
		</p>
		<p>
			<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_EXPORTBEFORE') ?>
		</p>
		<?php if(!empty($otherInfo) && ($this->type == 'user')): ?>
			<p class="alert alert-info">
				<?= $otherInfo ?>
			</p>
		<?php endif ?>
		<p>
			<?= Text::sprintf(($this->type == 'user') ? 'COM_DATACOMPLIANCE_OPTIONS_WIPE_ASKFORPHRASE' : 'COM_DATACOMPLIANCE_OPTIONS_WIPE_ASKFORPHRASE_ADMIN', Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_CONFIRMPHRASE')); ?>
		</p>
		<p class="akeeba-block--warning">
			<?= Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_YOURUSER', 'badge bg-dark', $this->user->username) ?>
		</p>

		<form method="post"
			  action="<?= Route::_('index.php?option=com_datacompliance&view=options&task=wipe') ?>"
		>
			<div class="row">
				<label for="datacompliance-phrase" class="col-sm-3 col-form-label">
					<?= Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_PHRASELABEL') ?>
				</label>
				<div class="col-sm-9">
					<input id="datacompliance-phrase" type="text" name="phrase" class="form-control" value="" />
				</div>
			</div>

			<div class="row">
				<div class="col-sm-9 offset-sm-3">
					<button type="submit" class="btn btn-danger">
						<?= Text::_(($this->type == 'user') ? 'COM_DATACOMPLIANCE_OPTIONS_WIPE_BTN_PROCEED' : 'COM_DATACOMPLIANCE_OPTIONS_WIPE_BTN_PROCEED_ADMIN') ?>
					</button>
				</div>
			</div>

			<?= HTMLHelper::_('form.token') ?>
			<input type="hidden" name="user_id" value="<?= (int)$this->user->id ?>" />
		</form>
	</div>
</div>
