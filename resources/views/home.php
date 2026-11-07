@extends('layouts/app', [
'title' => $title,
'year' => $year,
])

@section('content')
<section class="hero">
    <h1>{{ $heading }}</h1>

    <p>{{ $description }}</p>

    <span class="status">{{ $status }}</span>
</section>
@endsection