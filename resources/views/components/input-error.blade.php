@props(['messages', 'surface' => 'light'])

@if ($messages)
    <ul {{ $attributes->merge([
        'class' => 'space-y-1 text-sm '.($surface === 'dark' ? 'text-red-300' : 'text-red-600'),
    ]) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
