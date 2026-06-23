<div class="sidebar d-flex flex-column p-2">
	<h4 class="text-white text-center">
		<img src="{{asset('Images/logo2.svg')}}" alt="" class="w-100">
	</h4>
	<ul class="nav nav-pills flex-column">
			<li class="nav-item">
				<a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
					<i class="fas fa-tachometer-alt"></i> داشبورد
				</a>
			</li>
	</ul>
</div>