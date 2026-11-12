@props(['product' => null])
@if($image = $product?->images->first())
    <img {{ $attributes->class(['finish-image']) }} src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt }}" width="400" height="300" loading="lazy">
@else
    <div {{ $attributes->class(['finish-placeholder']) }}><x-icon name="image" /><span>Sin fotografía</span></div>
@endif
