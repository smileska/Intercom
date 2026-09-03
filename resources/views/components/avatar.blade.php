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
        @php
            $p = $user->id === auth()->id() ? $user->presence(auth()->id()) : $user->presence();
            $presenceDot = [
                'online' => 'bg-green-500',
                'away' => 'bg-amber-400',
                'dnd' => 'bg-red-500',
                'invisible' => 'bg-white ring-1 ring-inset ring-gray-400 dark:bg-gray-900',
                'offline' => 'bg-gray-400',
            ][$p] ?? 'bg-gray-400';
        @endphp
        <span class="absolute -bottom-0.5 -right-0.5 block h-2.5 w-2.5 rounded-full border-2 border-white dark:border-gray-900 {{ $presenceDot }}"></span>
    @endif
</span>
