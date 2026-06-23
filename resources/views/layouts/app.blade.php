<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="initial-scale=1.0,maximum-scale=1,minimum-scale=1.0,user-scalable=no,width=device-width,viewport-fit=cover">
	<title>@yield('title', 'داشبورد')</title>
	@vite(['resources/css/app.css'])
	@stack('styles')
</head>
<body>
<div class="d-flex">
	{{-- سایدبار --}}
	@include('layouts.sidebar')

	{{-- محتوای اصلی --}}
	<div class="main-content flex-grow-1">
		@yield('content')
	</div>
</div>
@vite(['resources/js/app.js'])



@if ($errors->any())
	<script>
		Swal.fire( {
			title: 'خطا',
			icon: 'error',
			html: `
        <ul style="text-align:right">
            @foreach ($errors->all() as $error)
			<li>{{ $error }}</li>
            @endforeach
			</ul>
`,
			customClass: {
				confirmButton: 'btn btn-primary waves-effect waves-light'
			},
			buttonsStyling: false
		} );
	</script>
@endif

@if(session()->has('success') || session()->has('error') || session()->has('warning'))
	<script>
		Swal.fire( {
			toast: true,
			position: 'top-end',
			icon: "{{ session('success') ? 'success' : (session('error') ? 'error' : 'warning') }}",
			title: "{{ session('success') ?? session('error') ?? session('warning') }}",
			showConfirmButton: false,
			timer: 3000,
			timerProgressBar: true,
		} );
	</script>
@endif


@if($errors->any())
	<script>
		Swal.fire( {
			title: 'خطا',
			icon: 'error',
			html: `
            <ul style="text-align:right">
                @foreach ($errors->all() as $error)
			<li>{{ $error }}</li>
                @endforeach
			</ul>
`,
			confirmButtonText: 'باشه',
			customClass: {
				confirmButton: 'btn btn-primary waves-effect waves-light'
			},
		} );
	</script>
@endif

<script>
	window.alert = function(message, icon = 'error') {
		Swal.fire( {
			toast: true,
			position: 'top-end',
			icon: icon,
			title: message,
			showConfirmButton: false,
			timer: 4000,
			timerProgressBar: true
		} );
	};

	window.confirm = function(message) {
		return Swal.fire( {
			title: message,
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'بله',
			cancelButtonText: 'خیر',
			customClass: {
				confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
				cancelButton: 'btn btn-outline-secondary waves-effect'
			},
			buttonsStyling: false
		} ).then( result => result.isConfirmed );
	}
	document.addEventListener( 'DOMContentLoaded', function() {

		$( document ).on( 'submit', 'form[onsubmit*="confirm"]', async function(e) {
			e.preventDefault();

			const message = ( this.getAttribute( 'onsubmit' )
			                      .match( /confirm\(['"](.*?)['"]\)/ ) || [] )[1] || 'آیا مطمئن هستید؟';

			const ok = await window.confirm( message );
			if ( !ok ) return;
			this.submit();
		} );
	} );

</script>

@stack('js')@stack('script')@stack('scripts')

</body>
</html>