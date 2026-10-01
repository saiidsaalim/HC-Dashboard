@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-xl border border-white/20 bg-white/10 text-white shadow-sm placeholder:text-slate-400 focus:border-amber-300 focus:ring-amber-300']) }}>
