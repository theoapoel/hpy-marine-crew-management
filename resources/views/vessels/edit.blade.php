@extends('layouts.app')

@section('title', 'Edit Vessel · HPYMarine')
@section('heading', 'Vessels')

@section('content')
  <div>
    <a href="{{ route('vessels.show', $vessel->name) }}" class="text-xs text-muted hover:text-slate-900">← Back to {{ $vessel->vessel_name ?? $vessel->name }}</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">Edit {{ $vessel->vessel_name ?? $vessel->name }}</h1>
  </div>

  <form action="{{ route('vessels.update', $vessel->name) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf @method('PUT')
    @include('vessels.form', ['submitLabel' => 'Update Vessel'])
  </form>
@endsection
