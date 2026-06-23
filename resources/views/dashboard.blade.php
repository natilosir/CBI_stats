@php
	$currentHost = request()->getHost();
	$isCodalDomain = ($currentHost === 'codal.borzan.ir');
@endphp

@extends('layouts.app')

@section('title', 'داشبورد')

@section('content')
	<div class="container-fluid">
		<h2 class="mb-4">داشبورد مدیریت گزارش سهام</h2>

		@unless($isCodalDomain)
			<div class="card shadow-sm">
				<div class="card-body text-center">
					<p class="lead">ابتدا لیست سهام را دریافت و سپس گزارش‌های کدال را به‌صورت خودکار پردازش کنید.</p>
					<p class="text-muted mt-2">این عملیات ممکن است چند دقیقه طول بکشد.</p>
				</div>
			</div>
		@endunless

	</div>
@stop