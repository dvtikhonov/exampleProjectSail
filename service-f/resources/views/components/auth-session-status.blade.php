@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800']) }} role="status">
        {{ $status }}
    </div>
@endif
