@extends('layouts.app')

@section('title', 'Ketentuan Reservasi - Embung Bansari')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Ketentuan Reservasi Camping</h1>
    <p class="mt-2 text-gray-600">Embung Bansari</p>

    <article class="mt-6 rounded-lg border border-gray-200 bg-white p-6">
        @include('components.reservation-terms')
    </article>
</div>
@endsection