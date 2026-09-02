<x-auth-card>
    <h2 class="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-100">Потврди ја е-поштата</h2>

    <p class="text-sm text-gray-600 dark:text-gray-400">
        Испративме врска за потврда на <span class="font-medium">{{ auth()->user()->email }}</span>.
        Кликни на неа за да го активираш профилот.
    </p>

    @if (session('status') === 'verification-link-sent')
        <div class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/40 dark:text-green-300">
            Нова врска за потврда беше испратена.
        </div>
    @elseif (session('status') === 'verification-link-failed')
        <div class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/40 dark:text-red-300">
            Не успеавме да ја испратиме е-поштата (проблем со mail серверот). Обиди се повторно подоцна.
        </div>
    @endif

    <div class="mt-6 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="rounded-md bg-[#4A154B] px-4 py-2 text-sm font-medium text-white hover:bg-[#3a1039]">
                Испрати повторно
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700 dark:text-gray-400">
                Одјава
            </button>
        </form>
    </div>
</x-auth-card>
