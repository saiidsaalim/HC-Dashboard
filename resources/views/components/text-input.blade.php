@props(['disabled' => false, 'surface' => 'light'])

<input @disabled($disabled) {{ $attributes->merge([
    'class' => 'mt-1 block min-h-11 w-full rounded-xl border shadow-sm transition duration-150 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 '.($surface === 'light'
        ? 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-500 focus:border-amber-400 focus:ring-amber-400'
        : 'border-white/25 bg-white/10 text-white placeholder:text-slate-400 hover:border-white/40 focus:border-amber-300 focus:ring-amber-300'),
]) }}>
