@php
	$currentHost = request()->getHost();
	$isCodalDomain = ($currentHost === 'codal.borzan.ir');
@endphp

<div class="sidebar d-flex flex-column p-2">
	<h4 class="text-white text-center">
		<img src="{{asset('Images/logo2.svg')}}" alt="" class="w-100">
	</h4>
	<ul class="nav nav-pills flex-column">
		@unless($isCodalDomain)
			<li class="nav-item">
				<a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
					<i class="fas fa-tachometer-alt"></i> داشبورد
				</a>
			</li>

{{-- 			<li class="nav-item"> --}}
{{-- 				<a class="nav-link" href="{{route('stock.GetStock')}}"> --}}
{{-- 					<i class="fas fa-database"></i> سهام‌ها --}}
{{-- 				</a> --}}
{{-- 			</li> --}}

			<li class="nav-item">
				<a class="nav-link {{ request()->routeIs('reports.batch') ? 'active' : '' }}" href="{{ route('reports.batch', ['offset' => 0, 'page' => 1]) }}">
					<i class="fas fa-file-alt"></i> گزارش‌ها
				</a>
			</li>

			<li class="nav-item">
				<a class="nav-link {{ request()->routeIs('reports.download.page') ? 'active' : '' }}" href="{{ route('reports.download.page') }}">
					<i class="fas fa-download"></i> دانلود گزارش‌ها
				</a>
			</li>

			<li class="nav-item">
				<a class="nav-link {{ request()->routeIs('reports.upload.zip') ? 'active' : '' }}" href="{{ route('reports.upload.zip') }}">
{{--				<a class="nav-link {{ request()->routeIs('reports.upload.batch') ? 'active' : '' }}" href="{{ route('reports.upload.batch') }}">--}}
					<i class="fas fa-upload"></i> آپلود گزارش‌ها
				</a>
			</li>

{{-- 			<li class="nav-item"> --}}
{{-- 				<a class="nav-link {{ request()->routeIs('reports.upload.zip') ? 'active' : '' }}" href="{{ route('reports.upload.zip') }}"> --}}
{{-- 					<i class="fas fa-file-zipper"></i> آپلود فایل زیپ --}}
{{-- 				</a> --}}
{{-- 			</li> --}}
		@endunless
		<li class="nav-item">
			<a class="nav-link {{ request()->routeIs('reports.download.list') ? 'active' : '' }}" href="{{ route('reports.download.list') }}">
				<i class="fas fa-list"></i> لیست گزارش‌ها
			</a>
		</li>
		@unless($isCodalDomain)
			<li class="nav-item">
				<a class="nav-link {{ request()->routeIs('deploy') ? 'active' : '' }}" href="{{ route('deploy') }}">
					<i class="fab fa-github"></i> بروزرسانی برنامه
				</a>
			</li>
		@endunless

	</ul>
</div>