@extends('layouts.app')

@section('title', 'New Principal · HPYMarine')
@section('heading', 'Principals')

@section('content')
  <div>
    <a href="{{ route('principals.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Principals</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">New Principal</h1>
  </div>

  <form action="{{ route('principals.store') }}" method="POST" class="space-y-5">
    @csrf
    @include('principals.form')
  </form>
@endsection
