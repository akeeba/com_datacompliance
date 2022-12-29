<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Model\LifecycleModel;
use Akeeba\Component\DataCompliance\Administrator\Model\WipeModel;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Akeeba\Component\DataCompliance\Administrator\View\Lifecycle\HtmlView $this */

HTMLHelper::_('behavior.multiselect');

$user      = Factory::getApplication()->getIdentity();
$canManage = $user->authorise('wipe', 'com_datacompliance')
	|| $user->authorise('export', 'com_datacompliance');
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$nullDate  = $this->getModel()->getDbo()->getNullDate();

/** @var LifecycleModel $model */
$model = $this->getModel();
/** @var WipeModel $wipeModel */
$wipeModel = $this->getModel('Wipe');

$when = clone Factory::getDate($model->getState('filter.when', 'now') ?: 'now');

$i = 0;

$userLayout = new FileLayout('akeeba.datacompliance.common.user', JPATH_ADMINISTRATOR . '/components/com_datacompliance/layout');

?>
<form action="<?= Route::_('index.php?option=com_datacompliance&view=lifecycle'); ?>"
      method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?= LayoutHelper::render('joomla.searchtools.default', ['view' => $this]) ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<span class="visually-hidden"><?= Text::_('INFO'); ?></span>
						<?= Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
				<table class="table" id="articleList">
					<caption class="visually-hidden">
						<?= Text::_('COM_DATACOMPLIANCE_LIFECYCLE_TABLE_CAPTION'); ?>, <span
							id="orderedBy"><?= Text::_('JGLOBAL_SORTED_BY'); ?> </span>, <span
							id="filteredBy"><?= Text::_('JGLOBAL_FILTERED_BY'); ?></span>
					</caption>
					<thead>
					<tr>
						<th scope="col">
							<?= HTMLHelper::_('searchtools.sort', 'COM_DATACOMPLIANCE_LIFECYCLE_FIELD_USER_ID', 'id', $listDirn, $listOrder); ?>
						</th>
						<th scope="col">
							<?= HTMLHelper::_('searchtools.sort', 'COM_DATACOMPLIANCE_LIFECYCLE_FIELD_REGISTERDATE', 'registerDate', $listDirn, $listOrder); ?>
						</th>
						<th scope="col">
							<?= HTMLHelper::_('searchtools.sort', 'COM_DATACOMPLIANCE_LIFECYCLE_FIELD_LASTVISITDATE', 'lastvisitDate', $listDirn, $listOrder); ?>
						</th>
						<th scope="col">
							<?= Text::_('COM_DATACOMPLIANCE_LIFECYCLE_FIELD_CANDELETE') ?>
						</th>
						<?php if ($canManage): ?>
						<th scope="col">
							<?= Text::_('COM_DATACOMPLIANCE_LIFECYCLE_FIELD_OPTIONS') ?>
						</th>
						<?php endif ?>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($this->items as $item) : ?>
					<tr class="row<?= $i++ % 2; ?>" data-draggable-group="0">
						<td>
							<?= $userLayout->render([
								'user_id'    => $item->id,
								'showUserId' => true,
								'username'   => $item->username,
								'name'       => $item->name,
								'email'      => $item->email,
								'showLink'   => true,
							]) ?>
						</td>
						<td>
							<?= HTMLHelper::_('datacompliance.formatDate', $item->registerDate) ?>
						</td>
						<td>
							<?= HTMLHelper::_('datacompliance.formatDate', $item->lastvisitDate) ?>
						</td>
						<td class="text-center">
							<?= HTMLHelper::_('jgrid.published', $wipeModel->checkWipeAbility($item->id, 'lifecycle', $when), $i, '', false, 'cb'); ?>
						</td>
						<?php if($canManage): ?>
						<td>
							<a href="<?= Route::_(sprintf('index.php?option=com_datacompliance&view=options&user_id=%d', $item->id)) ?>"
							   class="btn btn-outline-warning">
								<span class="fa fa-user" aria-hidden="true"></span>
								<?= Text::_('COM_DATACOMPLIANCE_LIFECYCLE_BTN_OPTIONS_MANAGE') ?>
							</a>
						</td>
						<?php endif ?>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php // Load the pagination. ?>
				<?= $this->pagination->getListFooter(); ?>

				<?php endif; ?>

				<input type="hidden" name="task" value="">
				<input type="hidden" name="boxchecked" value="0">
				<?= HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
