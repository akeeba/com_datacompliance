<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Model\Lifecycle;
use Akeeba\DataCompliance\Admin\Model\Wipe;use FOF30\Utils\FEFHelper\BrowseView;
use FOF30\Utils\SelectOptions;

defined('_JEXEC') or die();

/**
 * @var  FOF30\View\DataView\Html $this
 * @var  Lifecycle                $row
 * @var  Lifecycle                $model
 * @var  Wipe                     $wipeModel
 */

$model     = $this->getModel();
$wipeModel = $this->getContainer()->factory->model('Wipe')->tmpInstance();
$when      = $this->getContainer()->platform->getDate($model->when);
?>

@extends('admin:com_datacompliance/Common/browse')

@section('browse-filters')
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('user_id', 'user_id', 'COM_DATACOMPLIANCE_LIFECYCLE_FIELD_USER_ID')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @jhtml('calendar', $model->when, 'when', 'when', '%Y-%m-%d', [
        'placeholder' => JText::_('COM_DATACOMPLIANCE_LIFECYCLE_FILTER_WHEN'),
        'onChange' => 'document.forms.adminForm.submit();']
        )
    </div>

@stop

@section('browse-table-header')
{{-- ### HEADER ROW ### --}}
<tr>
    {{-- Row select --}}
    <th width="20">
        @jhtml('FEFHelper.browse.checkall')
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
</tr>
@stop

@section('browse-table-body-withrecords')
{{-- Table body shown when records are present. --}}
<?php $i = 0; ?>
@foreach($this->items as $row)
    <?php $canDelete = $wipeModel->checkWipeAbility($row->id, 'lifecycle', $when) ?>
    <tr class="{{ $canDelete ? '' : 'akeeba-datacompliance-lifecycle-row-cantdelete' }}">
        <td>
            @jhtml('FEFHelper.browse.id', ++$i, $row->getId())
        </td>
        <td>
            @include('admin:com_datacompliance/Common/ShowUser', ['item' => $row, 'field' => 'id'])
        </td>
        <td>
            {{ \Akeeba\DataCompliance\Admin\Helper\Format::date($row->registerDate) }}
        </td>
        <td>
            {{ \Akeeba\DataCompliance\Admin\Helper\Format::date($row->lastvisitDate) }}
        </td>
        <td>
            @jhtml('FEFHelper.browse.published', $canDelete, $i, '', false)
            {{--@lang($canDelete ? 'JYES' : 'JNO')--}}
        </td>
    </tr>
@endforeach
@stop
