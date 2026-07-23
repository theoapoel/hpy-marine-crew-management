@extends('layouts.app')

@section('title', 'Add Candidate · HPYMarine')
@section('heading', 'Candidate Pool')

@section('content')
  <div>
    <a href="{{ route('candidates.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Candidate Pool</a>
    <h1 class="text-2xl font-semibold text-slate-900 mt-2">Add Candidate</h1>
  </div>

  <form action="{{ route('candidates.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @include('candidates.form')
  </form>
@endsection
