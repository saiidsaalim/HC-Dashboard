@props(['disabled' => false, 'surface' => 'dark'])

<input @disabled($disabled) {{ $attributes->merge([
    'class' => 'mt-1 block w-full rounded-xl border shadow-sm transition disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 '.($surface === 'light'
        ? 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-500 focus:border-amber-400 focus:ring-amber-400'
        : 'border-white/20 bg-white/10 text-white placeholder:text-slate-400 focus:border-amber-300 focus:ring-amber-300'),
]) }}>
