@props(['name', 'id', 'errorName' => null])
@php
    $fieldErrors = $errors->get($errorName ?? $name);
    $describedBy = trim(($attributes->get('aria-describedby') ?? '').' '.($fieldErrors ? $id.'-error' : ''));
@endphp
<select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except(['aria-describedby', 'aria-invalid'])->class(['gym-input', 'border-red-700' => count($fieldErrors) > 0]) }}
    @if ($fieldErrors) aria-invalid="true" @endif
    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>{{ $slot }}</select>
@if ($fieldErrors)
    <p class="mt-1 rounded-lg bg-red-50 px-2 py-1 text-sm text-red-800" id="{{ $id }}-error">{{ $fieldErrors[0] }}</p>
@endif
