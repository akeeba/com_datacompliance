<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use FOF30\Utils\FEFHelper\Html as FEFHtml;

/** @var  FOF30\View\DataView\Html  $this */

?>

{{-- Allow tooltips, used in grid headers --}}
@jhtml('behavior.tooltip')
{{-- Allow SHIFT+click to select multiple rows --}}
@jhtml('behavior.multiselect')


@section('browse-filters')
{{-- Filters above the table. --}}
@stop

@section('browse-table-header')
{{-- Table header. Column headers and optional filters displayed above the column headers. --}}
@stop

@section('browse-table-body-norecords')
{{-- Table body shown when no records are present. --}}
<tr>
    <td colspan="99">
        <?php echo JText::_($this->getContainer()->componentName . '_COMMON_NORECORDS') ?>
    </td>
</tr>
@stop

@section('browse-table-body-withrecords')
{{-- Table body shown when records are present. --}}
<?php $i = 0; ?>
@foreach($this->items as $row)
<tr>
    {{-- You need to implement me! --}}
</tr>
@endforeach
@stop

@section('browse-table-footer')
    {{-- Table footer. The default is showing the pagination footer. --}}
    <tr>
        <td colspan="99" class="center">
            {{ $this->pagination->getListFooter() }}
        </td>
    </tr>
@stop

@section('browse-hidden-fields')
    {{-- Put your additional hidden fields in this section --}}
@stop

@yield('browse-page-top')

{{-- Administrator form for browse views --}}
<form action="index.php" method="post" name="adminForm" id="adminForm" class="akeeba-form">
    {{-- Filters and ordering --}}
    <section class="akeeba-panel--33-66 akeeba-filter-bar-container">
        <div class="akeeba-filter-bar akeeba-filter-bar--left akeeba-form-section akeeba-form--inline">
            @yield('browse-filters')
        </div>
		<div class="akeeba-filter-bar akeeba-filter-bar--right">
			@jhtml('FEFHelper.browse.orderjs', $this->lists->order)
			@jhtml('FEFHelper.browse.orderheader', $this)
		</div>
	</section>

    <table class="akeeba-table akeeba-table--striped--hborder--hover" id="itemsList">
        <thead>
        @yield('browse-table-header')
        </thead>
        <tfoot>
        @yield('browse-table-footer')
        </tfoot>
        <tbody>
        @unless(count($this->items))
        @yield('browse-table-body-norecords')
        @else
        @yield('browse-table-body-withrecords')
        @endunless
        </tbody>
    </table>

    {{-- Hidden form fields --}}
    <div class="akeeba-hidden-fields-container">
        @section('browse-default-hidden-fields')
            <input type="hidden" name="option" id="option" value="{{{ $this->getContainer()->componentName }}}"/>
            <input type="hidden" name="view" id="view" value="{{{ $this->getName() }}}"/>
            <input type="hidden" name="boxchecked" id="boxchecked" value="0"/>
            <input type="hidden" name="task" id="task" value="{{{ $this->getTask() }}}"/>
            <input type="hidden" name="filter_order" id="filter_order" value="{{{ $this->lists->order }}}"/>
            <input type="hidden" name="filter_order_Dir" id="filter_order_Dir" value="{{{ $this->lists->order_Dir }}}"/>
            <input type="hidden" name="@token()" value="1"/>
        @show
        @yield('browse-hidden-fields')
    </div>
</form>

@yield('browse-page-bottom')
