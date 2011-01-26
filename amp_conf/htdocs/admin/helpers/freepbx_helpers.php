<?php

//helper for freepbx labels that show help text
function fpbx_label($text, $help = '') {
	return '<a href="javascript:void(null)" class="info">'
			. $text
			. '<span>'
			. $help
			. '</span></a>';
}

?>