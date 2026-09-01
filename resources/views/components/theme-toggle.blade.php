@props(['class' => ''])

<button type="button"
        x-data="{
            dark: document.documentElement.classList.contains('dark'),
            toggle() {
                this.dark = !this.dark;
                document.documentElement.classList.toggle('dark', this.dark);
                try { localStorage.setItem('theme', this.dark ? 'dark' : 'light'); } catch (e) {}
            }
        }"
        @click="toggle()"
        :title="dark ? 'Светла тема' : 'Темна тема'"
        {{ $attributes->merge(['class' => 'inline-flex h-8 w-8 items-center justify-center rounded-md transition '.$class]) }}>
    <svg x-show="dark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
         stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="4"></circle>
        <path stroke-linecap="round" d="M12 2v2m0 16v2M4 12H2m20 0h-2m-1.6-6.4-1.4 1.4M6.9 17.1l-1.4 1.4m0-12.9 1.4 1.4m10.6 10.6 1.4 1.4"></path>
    </svg>
    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
         stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"></path>
    </svg>
</button>
