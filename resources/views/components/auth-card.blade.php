<x-layouts.app>
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div class="relative text-center mb-6">
                <div class="absolute right-0 top-0">
                    <x-theme-toggle class="text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800" />
                </div>
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[#4A154B] text-white text-xl font-bold">C</div>
                <h1 class="mt-3 text-xl font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name', 'Company Intercom') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Интерна комуникација во компанијата</p>
            </div>

            <div class="bg-white shadow rounded-xl p-6 dark:bg-gray-900 dark:shadow-black/40">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.app>
