<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use FOF30\Utils\FEFHelper\Html as FEFHtml;

/** @var  FOF30\View\DataView\Html  $this */

?>

@section('edit-form-body')
    {{-- Put your form body in this section --}}
@stop

@section('edit-hidden-fields')
    {{-- Put your additional hidden fields in this section --}}
@stop

@yield('edit-page-top')

{{-- Administrator form for browse views --}}
<form action="index.php" method="post" name="adminForm" id="adminForm" class="akeeba-form--horizontal">
    {{-- Main form body --}}
    @yield('edit-form-body')
    {{-- Hidden form fields --}}
    <div class="akeeba-hidden-fields-container">
        @section('browse-default-hidden-fields')
            <input type="hidden" name="option" id="option" value="{{{ $this->getContainer()->componentName }}}"/>
            <input type="hidden" name="view" id="view" value="{{{ $this->getName() }}}"/>
            <input type="hidden" name="task" id="task" value="{{{ $this->getTask() }}}"/>
            <input type="hidden" name="id" id="id" value="{{{ $this->getItem()->getId() }}}"/>
            <input type="hidden" name="@token()" value="1"/>
        @show
        @yield('edit-hidden-fields')
    </div>
</form>

@yield('edit-page-bottom')
