<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Model\Wipetrails;
use FOF40\Html\FEFHelper\BrowseView;
use FOF40\Html\SelectOptions;

defined('_JEXEC') or die();

/**
 * @var  FOF40\View\DataView\Html $this
 * @var  Wipetrails               $row
 * @var  Wipetrails               $model
 */

$model = $this->getModel();
$typeOptions = [
	JHtml::_('select.option', 'user', JText::_('COM_DATACOMPLIANCE_WIPETRAILS_TYPE_OPT_USER')),
	JHtml::_('select.option', 'admin', JText::_('COM_DATACOMPLIANCE_WIPETRAILS_TYPE_OPT_ADMIN')),
	JHtml::_('select.option', 'lifecycle', JText::_('COM_DATACOMPLIANCE_WIPETRAILS_TYPE_OPT_LIFECYCLE')),
];
?>

@extends('any:lib_fof40/Common/browse')

@section('browse-filters')
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('user_id', 'user_id', 'COM_DATACOMPLIANCE_WIPETRAIL_FIELD_USER_ID')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('created_by', 'created_by', 'COM_DATACOMPLIANCE_WIPETRAIL_FIELD_CREATED_BY')
    </div>

    <th>
        {{ \FOF40\Html\FEFHelper\BrowseView::selectFilter('type', $typeOptions) }}
    </th>

@stop

@section('browse-table-header')
    {{-- ### HEADER ROW ### --}}
    <tr>
        {{-- Row select --}}
        <th width="20">
            @jhtml('FEFHelp.browse.checkall')
        </th>
        {{-- user_id --}}
        <th>
            @sortgrid('user_id')
        </th>
        {{-- type --}}
        <th>
            @sortgrid('type')
        </th>
        {{-- Created by --}}
        <th>
            @sortgrid('created_by')
        </th>
        {{-- Created on --}}
        <th width="20%">
            @sortgrid('created_on')
        </th>
        {{-- Requester IP --}}
        <th width="20%">
            @sortgrid('requester_ip')
        </th>
    </tr>
@stop

@section('browse-table-body-withrecords')
    {{-- Table body shown when records are present. --}}
	<?php $i = 0; ?>
    @foreach($this->items as $row)
        <tr>
            {{-- Row select --}}
            <td>
                @jhtml('FEFHelp.browse.id', ++$i, $row->getId())
            </td>
            {{-- User ID --}}
            <td>
                @include('any:lib_fof40/Common/user_show', ['item' => $row, 'field' => 'user_id', 'linkURL' => 'index.php?option=com_datacompliance&view=Wipetrail&task=read&id=[ITEM:ID]'])
            </td>
            {{--type--}}
            <td>
            <span class="akeeba-label--{{ ($row->type == 'user') ? 'green' : (($row->type == 'admin') ? 'red' : 'grey') }}">
            @lang('COM_DATACOMPLIANCE_WIPETRAILS_TYPE_OPT_' . $row->type)
            </span>
            </td>
            {{-- Created by --}}
            <td>
                @include('any:lib_fof40/Common/user_show', ['item' => $row, 'field' => 'created_by'])
            </td>
            {{-- Created on --}}
            <td>
                {{ \Akeeba\DataCompliance\Admin\Helper\Format::date($row->created_on) }}
            </td>
            {{-- Requester IP --}}
            <td>
                {{{ $row->requester_ip }}}
            </td>
        </tr>
    @endforeach
@stop
