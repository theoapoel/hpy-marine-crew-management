@extends('layouts.app')

@section('title', 'Edit Penugasan · HPYMarine')
@section('heading', 'Crew Assignment')

@section('content')
  <div>
    <a href="{{ route('assignments.show', $assignment) }}" class="text-xs text-muted hover:text-slate-900">← Back to {{ $assignment->assignment_code }}</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">Edit {{ $assignment->assignment_code }}</h1>
  </div>

  <form action="{{ route('assignments.update', $assignment) }}" method="POST" class="space-y-5">
    @csrf @method('PUT')
    @include('assignments.form', ['submitLabel' => 'Perbarui Penugasan'])
  </form>
@endsection
