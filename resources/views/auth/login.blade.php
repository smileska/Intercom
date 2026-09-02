<x-auth-card>
    <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100">Најава</h2>

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/40 dark:text-red-300">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/40 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Е-пошта</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Лозинка</label>
            <x-password-input name="password" autocomplete="current-password" required />
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <input type="checkbox" name="remember" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            Запомни ме
        </label>

        <button type="submit"
                class="w-full rounded-md bg-[#4A154B] px-4 py-2 text-white font-medium hover:bg-[#3a1039]">
            Најави се
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
        Немаш профил? <a href="{{ route('register') }}" class="text-[#4A154B] font-medium dark:text-purple-300">Регистрирај се</a>
    </p>
</x-auth-card>
