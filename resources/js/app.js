import jQuery from 'jquery';
import * as bootstrap from 'bootstrap';
import 'select2';
import 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';

import Swal from 'sweetalert2';

window.$         = window.jQuery = jQuery;
window.bootstrap = bootstrap;
window.Swal      = Swal;

$.extend( true, $.fn.dataTable.defaults, {
	processing: true,
	serverSide: true,
	responsive: true,
	pagingType: 'simple_numbers',
	pageLength: 20,
	lengthMenu: [ 20, 40, 70, 100, 150, 200, 400, 1000, 5000, 10000 ],
	language: {
		url: '/vendor/datatables/i18n/fa.json'
	}
} );