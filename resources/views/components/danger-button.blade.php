<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-button ui-button-danger inline-flex items-center px-4 py-2 text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
