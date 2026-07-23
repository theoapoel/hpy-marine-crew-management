@extends('layouts.app')

@section('title', 'Edit Candidate · HPYMarine')
@section('heading', 'Candidate Pool')

@section('content')
  <div>
    <a href="{{ route('candidates.show', $candidate) }}" class="text-xs text-muted hover:text-slate-900">← Back to {{ $candidate->candidate_code }}</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">Edit {{ $candidate->full_name }}</h1>
  </div>

  <form action="{{ route('candidates.update', $candidate) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf @method('PUT')
    @include('candidates.form')
  </form>
@endsection
