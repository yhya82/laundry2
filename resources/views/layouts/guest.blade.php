<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laundry Manager') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased bg-bg">
        <div class="min-h-screen flex flex-col items-center justify-center px-4">
            <div class="w-full max-w-sm">
                <div class="flex flex-col items-center gap-2 mb-8">
                    @if ($logoPath = \App\Models\Setting::get('branding.logo_path'))
                        <img src="{{ Storage::url($logoPath) }}" alt="" class="w-14 h-14 rounded-lg object-cover">
                    @else
                        <div class="w-14 h-14 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center text-2xl font-bold">
                            {{ Str::substr(\App\Models\Setting::get('branding.business_name', config('app.name')), 0, 1) }}
                        </div>
                    @endif
                    <div class="text-lg font-bold text-ink">{{ \App\Models\Setting::get('branding.business_name', config('app.name')) }}</div>
                    <div class="text-sm text-ink-muted">Welcome back</div>
                </div>

                <div class="bg-surface border border-line rounded-2xl shadow-sm px-7 py-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
