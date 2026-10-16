<button {{ $attributes->merge(['type' => 'button', 'class' => 'ui-button ui-button-secondary inline-flex items-center px-4 py-2 text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
