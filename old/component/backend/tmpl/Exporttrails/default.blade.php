<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Model\Exporttrails;
use FOF40\Html\FEFHelper\BrowseView;
use FOF40\Html\SelectOptions;

defined('_JEXEC') or die();

/**
 * @var  FOF40\View\DataView\Html $this
 * @var  Exporttrails            $row
 * @var  Exporttrails            $model
 */

$model = $this->getModel();
?>

@extends('any:lib_fof40/Common/browse')

@section('browse-filters')
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('user_id', 'user_id', 'COM_DATACOMPLIANCE_EXPORTTRAIL_FIELD_USER_ID')
    </div>

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
            @include('any:lib_fof40/Common/user_show', ['item' => $row, 'field' => 'user_id'])
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
