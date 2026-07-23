@extends('layouts.app')

@section('title', 'Penugasan Baru · HPYMarine')
@section('heading', 'Crew Assignment')

@section('content')
  <div>
    <a href="{{ route('assignments.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Crew Assignment</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">Penugasan Baru</h1>
  </div>

  <form action="{{ route('assignments.store') }}" method="POST" class="space-y-5">
    @csrf
    @include('assignments.form', ['submitLabel' => 'Simpan Penugasan'])
  </form>
@endsection
