<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Model\Lifecycle;
use Akeeba\DataCompliance\Admin\Model\Wipe;

defined('_JEXEC') or die();

/**
 * @var  FOF40\View\DataView\Html $this
 * @var  Lifecycle                $row
 * @var  Lifecycle                $model
 * @var  Wipe                     $wipeModel
 */

$model       = $this->getModel();
$wipeModel   = $this->getContainer()->factory->model('Wipe')->tmpInstance();
$when        = $this->getContainer()->platform->getDate($model->when);
$currentUser = $this->getContainer()->platform->getUser();
$canManage   = $currentUser->authorise('wipe', 'com_datacompliance') || $currentUser->authorise('export', 'com_datacompliance');
?>

@extends('any:lib_fof40/Common/browse')

@section('browse-filters')
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('user_id', 'user_id', 'COM_DATACOMPLIANCE_LIFECYCLE_FIELD_USER_ID')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @jhtml('calendar', $model->when, 'when', 'when', '%Y-%m-%d', [
        'placeholder' => \Joomla\CMS\Language\Text::_('COM_DATACOMPLIANCE_LIFECYCLE_FILTER_WHEN'),
        'class' => 'akeebaCommonEventsOnChangeSubmit',
        ])
    </div>

@stop

@section('browse-table-header')
{{-- ### HEADER ROW ### --}}
<tr>
    {{-- Row select --}}
    <th width="20">
        @jhtml('FEFHelp.browse.checkall')
    </th>
    <th>
        @sortgrid('user_id')
    </th>
    <th>
        @sortgrid('registerDate')
    </th>
    <th>
        @sortgrid('lastVisitDate')
    </th>
    <th>
        @lang('COM_DATACOMPLIANCE_LIFECYCLE_FIELD_CANDELETE')
    </th>
    @if($canManage)
    <th>
        @lang('COM_DATACOMPLIANCE_LIFECYCLE_FIELD_OPTIONS')
    </th>
    @endif
</tr>
@stop

@section('browse-table-body-withrecords')
{{-- Table body shown when records are present. --}}
<?php $i = 0; ?>
@foreach($this->items as $row)
    <?php $canDelete = $wipeModel->checkWipeAbility($row->id, 'lifecycle', $when) ?>
    <tr class="{{ $canDelete ? '' : 'akeeba-datacompliance-lifecycle-row-cantdelete' }}">
        <td>
            @jhtml('FEFHelp.browse.id', ++$i, $row->getId())
        </td>
        <td>
            @include('any:lib_fof40/Common/user_show', ['item' => $row, 'field' => 'id'])
        </td>
        <td>
            {{ \Akeeba\DataCompliance\Admin\Helper\Format::date($row->registerDate) }}
        </td>
        <td>
            {{ \Akeeba\DataCompliance\Admin\Helper\Format::date($row->lastvisitDate) }}
        </td>
        <td>
            @jhtml('FEFHelp.browse.published', $canDelete, $i, '', false)
        </td>
        @if($canManage)
        <td>
            <a href="index.php?option=com_datacompliance&view=Options&user_id={{{ $row->id }}}"
                class="akeeba-btn--orange">
                <span class="akion-person-stalker"></span>
                @lang('COM_DATACOMPLIANCE_LIFECYCLE_BTN_OPTIONS_MANAGE')
            </a>
        </td>
        @endif
    </tr>
@endforeach
@stop
