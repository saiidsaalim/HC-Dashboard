<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-xl border border-amber-300/50 bg-amber-400 px-5 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-950 shadow-lg shadow-amber-950/20 transition duration-150 hover:bg-amber-300 focus:bg-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2 focus:ring-offset-slate-900 active:bg-amber-500']) }}>
    {{ $slot }}
</button>
