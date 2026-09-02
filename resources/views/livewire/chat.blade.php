<div class="flex h-screen w-full overflow-hidden bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100"
     x-data="{
        toasts: [],
        baseTitle: document.title,
        addToast(d) {
            const id = Date.now() + Math.random();
            this.toasts.push(Object.assign({ id }, d));
            this.ping();
            if (window.Notification && Notification.permission === 'granted' && document.hidden) {
                try { new Notification(d.name, { body: d.body }); } catch (e) {}
            }
            setTimeout(() => this.dismiss(id), 6000);
        },
        dismiss(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
        ping() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'sine'; o.frequency.value = 680;
                g.gain.setValueAtTime(0.06, ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
                o.start(); o.stop(ctx.currentTime + 0.3);
            } catch (e) {}
        },
        setUnread(n) {
            document.title = (n > 0 ? '(' + n + ') ' : '') + this.baseTitle;
        }
     }"
     x-init="if (window.Notification && Notification.permission === 'default') { Notification.requestPermission(); }"
     @dm-notification.window="addToast($event.detail)"
     @unread-total.window="setUnread($event.detail.count)"
     wire:poll.3s="heartbeat">

    <aside class="w-72 shrink-0 bg-[#3F0E40] text-gray-200 flex flex-col dark:bg-[#1a0b1b]">
        <div class="px-4 py-4 border-b border-white/10 flex items-center justify-between gap-2">
            <button type="button" wire:click="openProfile({{ auth()->id() }})"
                    class="flex items-center gap-2 min-w-0 text-left group">
                <x-avatar :user="auth()->user()" :presence="true" class="h-9 w-9 text-xs" />
                <span class="min-w-0">
                    <span class="block font-bold text-white leading-tight truncate">{{ config('app.name') }}</span>
                    <span class="block text-xs text-purple-300 truncate group-hover:underline">{{ auth()->user()->name }}</span>
                </span>
            </button>
            <div class="flex items-center gap-1 shrink-0">
                <x-theme-toggle class="text-purple-200 hover:bg-white/10" />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Одјава"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-purple-200 hover:bg-white/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0 4-4m-4 4 4 4m6-11h4a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        @if ($totalUnread > 0)
            <div class="px-4 py-2 text-xs text-purple-200 bg-white/5">
                Имаш <span class="font-bold text-white">{{ $totalUnread }}</span> непрочитан{{ $totalUnread === 1 ? 'а порака' : 'и пораки' }}
            </div>
        @endif

        <div class="flex-1 overflow-y-auto px-2 py-3 space-y-6">
            <div>
                <div class="flex items-center justify-between px-2 mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-purple-300">Канали</span>
                    <button wire:click="toggleCreateChannel" class="text-purple-300 hover:text-white text-lg leading-none" title="Нов канал">+</button>
                </div>
                <ul class="space-y-0.5">
                    @foreach ($channels as $channel)
                        <li>
                            <button
                                wire:click="selectChannel({{ $channel->id }})"
                                class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-sm
                                       {{ $activeType === 'channel' && $activeChannelId === $channel->id ? 'bg-[#1164A3] text-white' : 'hover:bg-white/10 text-gray-200' }}">
                                <span>{{ $channel->is_private ? '🔒' : '#' }}</span>
                                <span class="truncate">{{ $channel->name }}</span>
                                @if ($pendingByChannel->get($channel->id, 0) > 0)
                                    <span class="ml-auto inline-flex min-w-[1.25rem] justify-center rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                                          title="Предлози за членство на чекање">
                                        {{ $pendingByChannel->get($channel->id) }}
                                    </span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <div class="px-2 mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-purple-300">Директни пораки</span>
                </div>

                <div class="px-2 mb-2 relative">
                    <input type="text" wire:model.live.debounce.300ms="userSearch"
                           placeholder="Пребарај луѓе..."
                           class="w-full rounded-md border-0 bg-white/10 px-3 py-1.5 pr-8 text-sm text-white placeholder-purple-300 focus:bg-white/20 focus:ring-1 focus:ring-purple-300">
                    @if ($userSearch !== '')
                        <button wire:click="$set('userSearch', '')"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-purple-300 hover:text-white text-sm">✕</button>
                    @endif
                </div>

                @if (trim($userSearch) !== '')
                    <ul class="space-y-0.5">
                        @forelse ($people as $person)
                            <li>
                                <button wire:click="selectUser({{ $person->id }})"
                                        class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-white/10 text-gray-200">
                                    <x-avatar :user="$person" :presence="true" class="h-5 w-5 text-[10px]" />
                                    <span class="truncate">{{ $person->name }}</span>
                                    @if ($person->title)
                                        <span class="ml-auto text-[10px] text-purple-300 truncate">{{ $person->title }}</span>
                                    @endif
                                </button>
                            </li>
                        @empty
                            <li class="px-2 py-1.5 text-xs text-purple-300">Нема резултати за „{{ $userSearch }}“.</li>
                        @endforelse
                    </ul>
                @else
                    <ul class="space-y-0.5">
                        @forelse ($conversations as $c)
                            <li>
                                <button
                                    wire:click="selectUser({{ $c->user->id }})"
                                    class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-sm
                                           {{ $activeType === 'dm' && $activeUserId === $c->user->id ? 'bg-[#1164A3] text-white' : 'hover:bg-white/10 text-gray-200' }}">
                                    <x-avatar :user="$c->user" :presence="true" class="h-5 w-5 text-[10px]" />
                                    <span class="truncate {{ $c->unread > 0 ? 'font-semibold text-white' : '' }}">{{ $c->user->name }}</span>
                                    @if ($c->unread > 0)
                                        <span class="ml-auto inline-flex min-w-[1.25rem] justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">
                                            {{ $c->unread > 99 ? '99+' : $c->unread }}
                                        </span>
                                    @endif
                                </button>
                            </li>
                        @empty
                            <li class="px-2 py-1.5 text-xs text-purple-300">Сè уште нема разговори. Пребарај некого погоре 👆</li>
                        @endforelse
                    </ul>
                @endif
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col bg-white min-w-0 dark:bg-gray-900">
        @if ($activeType === 'channel' && $openChannel)
            <header class="px-6 py-3 border-b flex items-center justify-between gap-3 dark:border-gray-700">
                <div class="min-w-0">
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100"># {{ $openChannel->name }}</h2>
                    @if ($openChannel->description)
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $openChannel->description }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if ($openChannel->is_private)
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded dark:bg-gray-800 dark:text-gray-400">🔒 приватен канал</span>
                    @endif
                    <button wire:click="openChannelMembers({{ $openChannel->id }})"
                            class="text-xs rounded-md border border-gray-300 px-2.5 py-1 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                        👥 Членови
                    </button>
                </div>
            </header>
        @elseif ($activeType === 'dm' && $openUser)
            <header class="px-6 py-3 border-b flex items-center gap-2 dark:border-gray-700">
                <button type="button" wire:click="openProfile({{ $openUser->id }})" class="flex items-center gap-2 group">
                    <x-avatar :user="$openUser" :presence="true" class="h-7 w-7 text-xs" />
                    <div class="text-left">
                        <h2 class="font-semibold text-gray-800 group-hover:underline dark:text-gray-100">{{ $openUser->name }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $openUser->isOnline() ? 'онлајн' : ($openUser->title ?: 'офлајн') }}
                        </p>
                    </div>
                </button>
            </header>
        @else
            <header class="px-6 py-3 border-b dark:border-gray-700">
                <h2 class="font-semibold text-gray-400">Избери канал или колега</h2>
            </header>
        @endif

        @error('access')
            <div class="px-6 py-2 bg-red-50 text-red-700 text-sm dark:bg-red-900/40 dark:text-red-300">{{ $message }}</div>
        @enderror

        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4" id="message-list"
             x-data
             x-init="
        const scrollToBottom = () => { $el.scrollTop = $el.scrollHeight };
        scrollToBottom();
        new MutationObserver(scrollToBottom).observe($el, { childList: true, subtree: true });">
            @forelse ($messages as $message)
                <div class="flex gap-3 group" wire:key="msg-{{ $message->id }}">
                    <button type="button" wire:click="openProfile({{ $message->sender_id }})" class="shrink-0">
                        <x-avatar :user="$message->sender" class="h-9 w-9 text-xs" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline gap-2">
                            <button type="button" wire:click="openProfile({{ $message->sender_id }})"
                                    class="font-semibold text-sm text-gray-800 hover:underline dark:text-gray-100">
                                {{ $message->sender->name }}
                            </button>
                            <span class="text-xs text-gray-400">{{ $message->created_at->format('H:i') }}</span>
                            @if ($message->is_edited)
                                <span class="text-xs text-gray-400 italic">(изменето)</span>
                            @endif
                        </div>

                        @if ($editingMessageId === $message->id)
                            <form wire:submit.prevent="saveEdit" class="mt-1 flex gap-2">
                                <input type="text" wire:model="editingBody"
                                       class="flex-1 rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                <button type="submit" class="text-xs text-blue-600 font-medium">Зачувај</button>
                                <button type="button" wire:click="cancelEdit" class="text-xs text-gray-500">Откажи</button>
                            </form>
                        @else
                            <p class="text-sm text-gray-700 whitespace-pre-wrap break-words dark:text-gray-300">{{ $message->body }}</p>
                        @endif
                    </div>

                    @if ($message->sender_id === auth()->id() && $editingMessageId !== $message->id)
                        <div class="opacity-0 group-hover:opacity-100 flex items-start gap-2 text-xs">
                            <button wire:click="startEdit({{ $message->id }})" class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">✎</button>
                            <button wire:click="deleteMessage({{ $message->id }})"
                                    wire:confirm="Да се избрише пораката?"
                                    class="text-gray-400 hover:text-red-600">🗑</button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400">Сè уште нема пораки. Испрати ја првата! 👋</p>
            @endforelse
        </div>

        @if (($activeType === 'channel' && $openChannel) || ($activeType === 'dm' && $openUser))
            <form wire:submit.prevent="sendMessage" class="border-t px-6 py-3 flex items-center gap-3 dark:border-gray-700">
                <input
                    type="text"
                    wire:model="newMessage"
                    placeholder="Напиши порака..."
                    autocomplete="off"
                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-[#4A154B] focus:ring-[#4A154B] text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                >
                <button type="submit" class="rounded-md bg-[#4A154B] px-4 py-2 text-sm font-medium text-white hover:bg-[#3a1039]">
                    Испрати
                </button>
            </form>
            @error('newMessage')
                <p class="px-6 pb-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        @endif
    </main>

    @if ($showCreateChannel)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="toggleCreateChannel">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 dark:bg-gray-800">
                <h3 class="font-semibold text-gray-800 mb-4 dark:text-gray-100">Нов канал</h3>

                <form wire:submit.prevent="createChannel" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Име на канал</label>
                        <input type="text" wire:model="newChannelName" placeholder="на пр. marketing"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        @error('newChannelName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Опис (опционално)</label>
                        <input type="text" wire:model="newChannelDescription"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" wire:model="newChannelPrivate" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        Приватен канал (само поканети членови)
                    </label>

                    @if ($newChannelPrivate)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-300">Членови</label>
                            <div class="max-h-32 overflow-y-auto border rounded-md p-2 space-y-1 dark:border-gray-600">
                                @foreach ($colleagues as $colleague)
                                    <label class="flex items-center gap-2 text-sm dark:text-gray-300">
                                        <input type="checkbox"
                                               value="{{ $colleague->id }}"
                                               wire:click="toggleMember({{ $colleague->id }})"
                                               @checked(in_array($colleague->id, $newChannelMembers))
                                               class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                        {{ $colleague->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="toggleCreateChannel" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Откажи</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-[#4A154B] text-white hover:bg-[#3a1039]">Создај</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showChannelMembers && $membersChannel)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" wire:click.self="closeChannelMembers">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto dark:bg-gray-800">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                        {{ $membersChannel->is_private ? '🔒' : '#' }} {{ $membersChannel->name }} · Членови
                    </h3>
                    <button wire:click="closeChannelMembers" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl">✕</button>
                </div>

                @error('members') <p class="mb-3 text-xs text-red-600">{{ $message }}</p> @enderror

                <ul class="space-y-1 mb-5">
                    @foreach ($memberRows as $member)
                        <li class="flex items-center gap-2 text-sm">
                            <x-avatar :user="$member" class="h-6 w-6 text-[10px]" />
                            <span class="truncate text-gray-700 dark:text-gray-200">{{ $member->name }}</span>
                            @if ($member->id === $membersChannel->created_by)
                                <span class="text-[10px] text-gray-400">(креатор)</span>
                            @endif
                            @if (auth()->user()->isAdmin() && $member->id !== $membersChannel->created_by)
                                <button wire:click="removeChannelMember({{ $member->id }})"
                                        class="ml-auto text-gray-400 hover:text-red-600" title="Отстрани од каналот">✕</button>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if (auth()->user()->isAdmin() && $pendingSuggestions->isNotEmpty())
                    <div class="mb-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Предлози на чекање</p>
                        <ul class="space-y-2">
                            @foreach ($pendingSuggestions as $suggestion)
                                <li class="flex items-center gap-2 text-sm">
                                    <x-avatar :user="$suggestion->user" class="h-6 w-6 text-[10px]" />
                                    <span class="min-w-0">
                                        <span class="block truncate text-gray-700 dark:text-gray-200">{{ $suggestion->user->name }}</span>
                                        <span class="block text-[10px] text-gray-400">предложи {{ $suggestion->suggestedBy->name }}</span>
                                    </span>
                                    <span class="ml-auto flex gap-1">
                                        <button wire:click="approveSuggestion({{ $suggestion->id }})"
                                                class="rounded bg-green-600 px-2 py-1 text-[11px] font-medium text-white hover:bg-green-700">Одобри</button>
                                        <button wire:click="rejectSuggestion({{ $suggestion->id }})"
                                                class="rounded bg-gray-200 px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200">Одбиј</button>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($candidates->isNotEmpty())
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">
                        {{ auth()->user()->isAdmin() ? 'Додади членови' : 'Предложи членови' }}
                    </p>
                    <ul class="space-y-1 max-h-52 overflow-y-auto">
                        @foreach ($candidates as $person)
                            <li class="flex items-center gap-2 text-sm">
                                <x-avatar :user="$person" class="h-6 w-6 text-[10px]" />
                                <span class="truncate text-gray-700 dark:text-gray-200">{{ $person->name }}</span>
                                @if (auth()->user()->isAdmin())
                                    <button wire:click="addChannelMember({{ $person->id }})"
                                            class="ml-auto rounded bg-[#4A154B] px-2 py-1 text-[11px] font-medium text-white hover:bg-[#3a1039]">Додади</button>
                                @elseif (in_array($person->id, $suggestedUserIds))
                                    <span class="ml-auto text-[11px] text-gray-400">Предложено</span>
                                @else
                                    <button wire:click="suggestMember({{ $person->id }})"
                                            class="ml-auto rounded border border-[#4A154B] px-2 py-1 text-[11px] font-medium text-[#4A154B] hover:bg-[#4A154B]/10 dark:border-purple-400 dark:text-purple-300">Предложи</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    @if ($showProfile && $profile)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" wire:click.self="closeProfile">
            <div class="w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-800">
                <div class="h-20 bg-[#4A154B]"></div>
                <div class="px-5 pb-5">
                    <div class="-mt-10 flex items-end justify-between">
                        <x-avatar :user="$profile" :presence="true"
                                  class="h-20 w-20 text-2xl ring-4 ring-white dark:ring-gray-800" />
                        <button wire:click="closeProfile" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl">✕</button>
                    </div>

                    <h3 class="mt-3 text-lg font-bold text-gray-900 dark:text-gray-100">{{ $profile->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $profile->isOnline() ? '🟢 онлајн' : '⚪ офлајн' }}
                        @if ($profile->title) · {{ $profile->title }} @endif
                    </p>

                    @if ($profile->bio)
                        <p class="mt-3 text-sm text-gray-700 whitespace-pre-wrap dark:text-gray-300">{{ $profile->bio }}</p>
                    @endif

                    <dl class="mt-4 space-y-1 text-sm">
                        <div class="flex gap-2">
                            <dt class="text-gray-400 w-16 shrink-0">Е-пошта</dt>
                            <dd class="text-gray-700 truncate dark:text-gray-300">{{ $profile->email }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-gray-400 w-16 shrink-0">Улога</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ $profile->isAdmin() ? 'Администратор' : 'Вработен' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex gap-2">
                        @if ($profile->id === auth()->id())
                            <button wire:click="openEditProfile"
                                    class="flex-1 rounded-md bg-[#4A154B] px-4 py-2 text-sm font-medium text-white hover:bg-[#3a1039]">
                                Уреди профил
                            </button>
                        @else
                            <button wire:click="messageUser({{ $profile->id }})"
                                    class="flex-1 inline-flex items-center justify-center gap-2 rounded-md bg-[#4A154B] px-4 py-2 text-sm font-medium text-white hover:bg-[#3a1039]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                                Порака
                            </button>
                        @endif
                    </div>

                    @if (auth()->user()->isAdmin() && $profile->id !== auth()->id())
                        <div class="mt-3">
                            <button wire:click="toggleAdmin({{ $profile->id }})"
                                    class="w-full rounded-md border px-4 py-2 text-sm font-medium
                                           {{ $profile->isAdmin()
                                              ? 'border-red-300 text-red-600 hover:bg-red-50 dark:border-red-500/50 dark:text-red-400 dark:hover:bg-red-900/20'
                                              : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700' }}">
                                {{ $profile->isAdmin() ? 'Отстрани од администратори' : 'Направи администратор' }}
                            </button>
                            @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($showEditProfile)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" wire:click.self="closeEditProfile">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Уреди профил</h3>
                    <button wire:click="closeEditProfile" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl">✕</button>
                </div>

                <form wire:submit.prevent="saveProfile" class="space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="h-16 w-16 shrink-0">
                            @if ($pAvatar)
                                <img src="{{ $pAvatar->temporaryUrl() }}" class="h-16 w-16 rounded-lg object-cover">
                            @else
                                <x-avatar :user="auth()->user()" class="h-16 w-16 text-lg" />
                            @endif
                        </div>
                        <div>
                            <label class="cursor-pointer text-sm text-[#4A154B] font-medium hover:underline dark:text-purple-300">
                                Смени слика
                                <input type="file" wire:model="pAvatar" accept="image/*" class="hidden">
                            </label>
                            <div wire:loading wire:target="pAvatar" class="text-xs text-gray-400">Се вчитува…</div>
                            @error('pAvatar') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Име</label>
                        <input type="text" wire:model="pName"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        @error('pName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Позиција</label>
                        <input type="text" wire:model="pTitle" placeholder="на пр. Backend Developer"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        @error('pTitle') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Кратко за тебе</label>
                        <textarea wire:model="pBio" rows="2"
                                  class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
                        @error('pBio') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Е-пошта</label>
                        <input type="email" wire:model="pEmail"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        @error('pEmail') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-400 mt-1">Ако ја смениш, ќе треба повторно да ја потврдиш.</p>
                    </div>

                    <hr class="dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Промени лозинка (опционално)</p>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Тековна лозинка</label>
                        <input type="password" wire:model="pCurrentPassword" autocomplete="current-password"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        @error('pCurrentPassword') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Нова лозинка</label>
                        <input type="password" wire:model="pPassword" autocomplete="new-password"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        @error('pPassword') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Потврди нова лозинка</label>
                        <input type="password" wire:model="pPasswordConfirmation" autocomplete="new-password"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-[#4A154B] focus:ring-[#4A154B] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeEditProfile" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Откажи</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-md bg-[#4A154B] text-white hover:bg-[#3a1039]">
                            <span wire:loading.remove wire:target="saveProfile">Зачувај</span>
                            <span wire:loading wire:target="saveProfile">Се зачувува…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="fixed bottom-4 right-4 z-[60] flex flex-col gap-2 w-80">
        <template x-for="t in toasts" :key="t.id">
            <div class="flex items-start gap-3 rounded-lg bg-white p-3 shadow-xl ring-1 ring-black/5 cursor-pointer
                        dark:bg-gray-800 dark:ring-white/10"
                 @click="$wire.messageUser(t.userId); dismiss(t.id)"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-x-4"
                 x-transition:enter-end="opacity-100 translate-x-0">
                <template x-if="t.avatar">
                    <img :src="t.avatar" class="h-9 w-9 rounded-lg object-cover shrink-0">
                </template>
                <template x-if="!t.avatar">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white"
                          :style="'background-color:' + t.color" x-text="t.initials"></span>
                </template>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="t.name"></p>
                    <p class="text-xs text-gray-600 truncate dark:text-gray-400" x-text="t.body"></p>
                </div>
                <button @click.stop="dismiss(t.id)" class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
            </div>
        </template>
    </div>

    <div x-data="{ show: false, msg: '' }"
         @flash.window="msg = $event.detail.message; show = true; setTimeout(() => show = false, 4000)"
         x-show="show" x-transition
         class="fixed top-4 left-1/2 -translate-x-1/2 z-[70] rounded-md bg-green-600 px-4 py-2 text-sm text-white shadow-lg"
         style="display: none;">
        <span x-text="msg"></span>
    </div>
</div>
