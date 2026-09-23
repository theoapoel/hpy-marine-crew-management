@extends('layouts.app')

@section('title', 'Edit Application · HPYMarine')
@section('heading', 'Recruitment Pipeline')

@section('content')
  <div>
    <a href="{{ route('applications.show', $application) }}" class="text-xs text-muted hover:text-slate-900">← Back to {{ $application->application_code }}</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">Edit {{ $application->application_code }}</h1>
  </div>

  <form action="{{ route('applications.update', $application) }}" method="POST" class="space-y-5">
    @csrf @method('PUT')
    @include('applications.form', ['submitLabel' => 'Update Application'])
  </form>
@endsection
