<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-critical-soft border border-transparent rounded-lg font-semibold text-sm text-critical hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-critical focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
