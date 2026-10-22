@extends('layouts.web')

@section('title', 'Dashboard — SMK PGRI Kras')

@section('content')
@if(auth()->user()->hasRole('guru'))
    @include('web.dashboard.guru')
@elseif(auth()->user()->hasRole('supervisor') && !auth()->user()->isWakaKurikulum())
    @include('web.dashboard.supervisor')
@else
    @include('web.dashboard.index')
@endif
@endsection