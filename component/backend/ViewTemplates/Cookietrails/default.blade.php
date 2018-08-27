<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Model\Cookietrails;
use FOF30\Utils\FEFHelper\BrowseView;
use FOF30\Utils\SelectOptions;

defined('_JEXEC') or die();

/**
 * @var  \Akeeba\DataCompliance\Admin\View\Cookietrail\Html $this
 * @var  Cookietrails                                       $row
 * @var  Cookietrails                                       $model
 */

$model = $this->getModel();
?>

@extends('admin:com_datacompliance/Common/browse')

@section('browse-filters')
    {{-- Created by --}}
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('created_by', 'created_by', 'COM_DATACOMPLIANCE_USERTRAIL_FIELD_USER_ID')
    </div>

@stop

@section('browse-table-header')
{{-- ### HEADER ROW ### --}}
<tr>
    {{-- Row select --}}
    <th width="20">
        @jhtml('FEFHelper.browse.checkall')
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
    {{-- Preference --}}
    <th width="10%">
        @sortgrid('preference')
    </th>
    {{-- DNT --}}
    <th width="10%">
        @sortgrid('dnt')
    </th>
    {{-- Reset --}}
    <th width="60">
        @sortgrid('reset')
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
            @jhtml('FEFHelper.browse.id', ++$i, $row->getId())
        </td>
        {{-- Created by --}}
        <td>
            @include('admin:com_datacompliance/Common/ShowUser', ['item' => $row, 'field' => 'created_by'])
        </td>
        {{-- Created on --}}
        <td>
            {{ \Akeeba\DataCompliance\Admin\Helper\Format::date($row->created_on) }}
        </td>
        {{-- Requester IP --}}
        <td>
            {{{ $row->requester_ip }}}
        </td>

        {{-- Preference --}}
        <td>
            @if($row->preference == 1)
                <a class="akeeba-btn--green--mini disabled akeebagrid hasTooltip" title="@lang('COM_DATACOMPLIANCE_COOKIETRAIL_FIELD_PREFERENCE_YES')">
                    <span class="akion-checkmark-round"></span>&nbsp;
                </a>
            @elseif($row->preference == 0)
                <a class="akeeba-btn--red--mini disabled akeebagrid hasTooltip" title="@lang('COM_DATACOMPLIANCE_COOKIETRAIL_FIELD_PREFERENCE_NO')">
                    <span class="akion-close-round"></span>&nbsp;
                </a>
            @endif
        </td>

        {{-- DNT --}}
        <td>
            @if($row->dnt == -2)
                <a class="akeeba-btn--orange--mini disabled akeebagrid hasTooltip" title="@lang('COM_DATACOMPLIANCE_COOKIETRAIL_FIELD_DNT_NA')">
                    <span class="akion-minus-round"></span>&nbsp;
                </a>
            @elseif($row->dnt == -1)
                <a class="akeeba-btn--grey--mini disabled akeebagrid hasTooltip" title="@lang('COM_DATACOMPLIANCE_COOKIETRAIL_FIELD_DNT_UNSET')">
                    <span class="akion-help"></span>&nbsp;
                </a>
            @elseif($row->dnt == 0)
                <a class="akeeba-btn--red--mini disabled akeebagrid hasTooltip" title="@lang('COM_DATACOMPLIANCE_COOKIETRAIL_FIELD_DNT_TRACK')">
                    <span class="akion-eye"></span>&nbsp;
                </a>
            @elseif($row->dnt == 1)
                <a class="akeeba-btn--green--mini disabled akeebagrid hasTooltip" title="@lang('COM_DATACOMPLIANCE_COOKIETRAIL_FIELD_DNT_NOTTRACK')">
                    <span class="akion-eye-disabled"></span>&nbsp;
                </a>
            @endif
        </td>

        {{-- Reset --}}
        <td>
            @if($row->reset == 1)
                <a class="akeeba-btn--green--mini disabled akeebagrid hasTooltip" title="@lang('COM_DATACOMPLIANCE_COOKIETRAIL_FIELD_RESET_YES')">
                    <span class="akion-checkmark-round"></span>&nbsp;
                </a>
            @elseif($row->reset == 0)
                <a class="akeeba-btn--grey--mini disabled akeebagrid hasTooltip" title="@lang('COM_DATACOMPLIANCE_COOKIETRAIL_FIELD_RESET_NO')">
                    <span class="akion-close-round"></span>&nbsp;
                </a>
            @endif

        </td>
    </tr>
@endforeach
@stop
