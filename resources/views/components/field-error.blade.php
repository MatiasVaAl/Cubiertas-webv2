@props(['name'])
@error($name)<p id="{{ str_replace('.', '-', $name) }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror
