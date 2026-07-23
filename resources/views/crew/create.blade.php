@extends('layouts.app')

@section('title', 'Add Crew · HPYMarine')
@section('heading', 'Crew Master')

@section('content')
  <div>
    <a href="{{ route('crew.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Crew Master</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">Add Crew Member</h1>
  </div>

  <form action="{{ route('crew.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @include('crew.form', ['submitLabel' => 'Save Crew'])
  </form>
@endsection
