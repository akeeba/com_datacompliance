<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * @var  Akeeba\DataCompliance\Admin\View\Usertrail\Html $this
 * @var  \Akeeba\DataCompliance\Admin\Model\Usertrails   $item
 */

$item = $this->item;

?>

<div class="akeeba-panel--teal">
    <header class="akeeba-block-header">
        <h3>
            @lang('COM_DATACOMPLIANCE_USERTRAIL_HEAD_BASIC')
        </h3>
    </header>
    <table class="akeeba-table--leftbold--striped" width="100%">
        <tr>
            <td>@fieldtitle('user_id')</td>
            <td>
                @include('any:lib_fof40/Common/user_show', ['item' => $item, 'field' => 'user_id', 'showLink' => 'false'])
            </td>
        </tr>
        <tr>
            <td>@fieldtitle('created_by')</td>
            <td>
                @include('any:lib_fof40/Common/user_show', ['item' => $item, 'field' => 'created_by', 'showLink' => 'false'])
            </td>
        </tr>
        <tr>
            <td>@fieldtitle('created_on')</td>
            <td>
                {{ \Akeeba\DataCompliance\Admin\Helper\Format::date($item->created_on) }}
            </td>
        </tr>
        <tr>
            <td>@fieldtitle('requester_ip')</td>
            <td>
                {{{ $item->requester_ip }}}
            </td>
        </tr>
    </table>
</div>

<div class="akeeba-panel--info">
    <header class="akeeba-block-header">
        <h3>
            @lang('COM_DATACOMPLIANCE_USERTRAIL_HEAD_CHANGES')
        </h3>
    </header>
    <table class="akeeba-table--leftbold--striped--hover" width="100%">
        <thead>
        <tr>
            <th>@lang('COM_DATACOMPLIANCE_USERTRAIL_HEAD_WHATCHANGED')</th>
            <th>@lang('COM_DATACOMPLIANCE_USERTRAIL_HEAD_BEFORE')</th>
            <th>@lang('COM_DATACOMPLIANCE_USERTRAIL_HEAD_AFTER')</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($item->items as $what => $info)
        <tr>
            <td>{{{ $what }}}</td>
            <td>
                @if(is_string($info['from']))
                {{{ $info['from'] }}}
                @elseif(is_null($info['from']))
                NULL
                @else
                <pre>{{ print_r($info['from'], true) }}</pre>
                @endif
            </td>
            <td>
                @if(is_string($info['to']))
                {{{ $info['to'] }}}
                @elseif(is_null($info['to']))
                NULL
                @else
                <pre>{{ print_r($info['to'], true) }}</pre>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>