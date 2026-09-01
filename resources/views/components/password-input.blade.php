@props(['name' => 'password', 'id' => null, 'autocomplete' => 'current-password'])

@php($id = $id ?? $name)

<div x-data="{ show: false }" class="relative mt-1">
    <input :type="show ? 'text' : 'password'"
           name="{{ $name }}"
           id="{{ $id }}"
           autocomplete="{{ $autocomplete }}"
           {{ $attributes->merge(['class' => 'w-full rounded-md border-gray-300 pr-10 shadow-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100']) }}>

    <button type="button" @click="show = !show" tabindex="-1"
            :aria-label="show ? 'Сокриј лозинка' : 'Прикажи лозинка'"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12Z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </svg>
        <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.7A9.5 9.5 0 0 1 12 5.5c6.5 0 9.5 6.5 9.5 6.5a15.8 15.8 0 0 1-3 3.9M6.5 6.5A15.8 15.8 0 0 0 2.5 12S5.5 18.5 12 18.5a9 9 0 0 0 3-.5"></path>
        </svg>
    </button>
</div>
