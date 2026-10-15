@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false])

@php
    $fieldId = $attributes->get('id', $name);
    $hasError = $errors->has($name);
@endphp

<div>
    <label class="gym-label" for="{{ $fieldId }}">{{ $label }}</label>
    <input
        {{ $attributes->merge([
            'class' => 'gym-input',
            'id' => $fieldId,
            'name' => $name,
            'type' => $type,
            'value' => $type === 'password' ? null : old($name, $value),
            'aria-invalid' => $hasError ? 'true' : 'false',
            'aria-describedby' => $hasError ? $fieldId.'-error' : null,
        ]) }}
        @required($required)
    >
    @error($name)
        <p class="mt-1 text-sm font-medium text-red-700" id="{{ $fieldId }}-error">{{ $message }}</p>
    @enderror
</div>
