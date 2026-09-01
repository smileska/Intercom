@props(['user', 'presence' => false])

<span {{ $attributes->merge(['class' => 'relative inline-flex shrink-0 rounded-lg']) }}>
    @if ($user->avatarUrl())
        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}"
             class="h-full w-full rounded-lg object-cover">
    @else
        <span class="flex h-full w-full items-center justify-center rounded-lg font-bold text-white"
              style="background-color: {{ $user->avatar_color }}">
            {{ $user->initials() }}
        </span>
    @endif

    @if ($presence)
        <span class="absolute -bottom-0.5 -right-0.5 block h-2.5 w-2.5 rounded-full border-2 border-white dark:border-gray-900
                     {{ $user->isOnline() ? 'bg-green-500' : 'bg-gray-400' }}"></span>
    @endif
</span>
