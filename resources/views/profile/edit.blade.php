<x-app-layout>
    <x-slot name="header">Profile</x-slot>

    <div class="space-y-6 max-w-xl">
        <div class="p-6 bg-surface border border-line rounded-2xl">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="p-6 bg-surface border border-line rounded-2xl">
            @include('profile.partials.update-password-form')
        </div>

        <div class="p-6 bg-surface border border-line rounded-2xl">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
