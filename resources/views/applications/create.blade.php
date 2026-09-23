@extends('layouts.app')

@section('title', 'New Application · HPYMarine')
@section('heading', 'Recruitment Pipeline')

@section('content')
  <div>
    <a href="{{ route('applications.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Pipeline</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">New Application</h1>
  </div>

  <form action="{{ route('applications.store') }}" method="POST" class="space-y-5">
    @csrf
    @include('applications.form', ['submitLabel' => 'Save Application'])
  </form>
@endsection
