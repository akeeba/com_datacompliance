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
            @lang('COM_DATACOMPLIANCE_WIPETRAIL_HEAD_BASIC')
        </h3>
    </header>
    <table class="akeeba-table--leftbold--striped" width="100%">
        <tr>
            <td>@fieldtitle('user_id')</td>
            <td>
                @include('any:lib_fof30/Common/user_show', ['item' => $item, 'field' => 'user_id', 'showLink' => 'false'])
            </td>
        </tr>
        <tr>
            <td>@fieldtitle('created_by')</td>
            <td>
                @include('any:lib_fof30/Common/user_show', ['item' => $item, 'field' => 'created_by', 'showLink' => 'false'])
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
            @lang('COM_DATACOMPLIANCE_WIPETRAIL_HEAD_DELETED')
        </h3>
    </header>
    @foreach ($item->items as $domain => $domainItems)
        <?php
        $extension = 'plg_datacompliance_' . strtolower($domain);
        $this->getContainer()->platform->loadTranslations($extension);
        ?>
        <h4>@lang($extension . '_DOMAINNAME')</h4>

        <table class="akeeba-table--leftbold--striped--hover" width="100%">
            <thead>
            <tr>
                <th>@lang('COM_DATACOMPLIANCE_WIPETRAIL_HEAD_WHATDELETED')</th>
                <th>@lang('COM_DATACOMPLIANCE_WIPETRAIL_HEAD_IDS')</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($domainItems as $what => $ids)
                <tr>
                    <td>{{{ $what }}}</td>
                    <td>
                        @if (empty($ids))
                            &mdash;
                        @elseif (is_array($ids))
                            <ul>
                            @foreach ($ids as $id)
                                <li>{{{ $id }}}</li>
                            @endforeach
                            </ul>
                        @else
                            {{{ $ids }}}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endforeach
</div>