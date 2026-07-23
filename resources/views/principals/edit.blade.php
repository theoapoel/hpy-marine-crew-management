@extends('layouts.app')

@section('title', 'Edit Principal · HPYMarine')
@section('heading', 'Principals')

@section('content')
  <div>
    <a href="{{ route('principals.show', $principal) }}" class="text-xs text-muted hover:text-slate-900">← Back to {{ $principal->principal_code }}</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">Edit {{ $principal->principal_name }}</h1>
  </div>

  <form action="{{ route('principals.update', $principal) }}" method="POST" class="space-y-5">
    @csrf @method('PUT')
    @include('principals.form')
  </form>
@endsection
