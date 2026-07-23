@extends('layouts.app')

@section('title', 'New Vessel · HPYMarine')
@section('heading', 'Vessels')

@section('content')
  <div>
    <a href="{{ route('vessels.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Fleet</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">New Vessel</h1>
  </div>

  <form action="{{ route('vessels.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @include('vessels.form', ['submitLabel' => 'Save Vessel'])
  </form>
@endsection
