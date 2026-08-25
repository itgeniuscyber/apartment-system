<script setup lang="ts">
import { router, usePage, usePoll } from '@inertiajs/vue3';
import { Bell, CalendarDays, CheckCheck, Search } from '@lucide/vue';
import { computed } from 'vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, User } from '@/types';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const page = usePage();
usePoll(10000, {
    only: ['notificationCenter'],
});
const user = computed(() => page.props.auth.user as User);
const notificationCenter = computed(() => page.props.notificationCenter);
const routeTitles: Record<string, string> = {
    '/dashboard': 'Financial overview',
    '/properties': 'Estates & units',
    '/tenants': 'Tenant management',
    '/leases': 'Leases & billing',
    '/finance': 'Finance & collections',
    '/maintenance': 'Maintenance desk',
    '/messages': 'Messages & notices',
    '/reports': 'Reports & analytics',
    '/security': 'Security centre',
    '/smart-access': 'Smart access',
    '/communication-settings': 'Communication settings',
    '/tenant/payments': 'Payments & receipts',
    '/tenant/requests': 'Maintenance requests',
    '/tenant/messages': 'Messages & notices',
    '/tenant/documents': 'Lease documents',
};
const pageTitle = computed(() => {
    const path = page.url.split('?')[0];

    return props.breadcrumbs.at(-1)?.title ?? routeTitles[path] ?? 'Overview';
});
const today = new Intl.DateTimeFormat('en-UG', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
}).format(new Date());
const isTenant = computed(
    () => !['administrator', 'landlord', 'manager'].includes(user.value.role),
);
const workspaceLabel = computed(() =>
    isTenant.value ? 'Resident workspace' : 'Portfolio workspace',
);
const roleLabel = computed(() =>
    isTenant.value
        ? 'Resident'
        : user.value.role === 'landlord'
          ? 'Landlord'
          : user.value.role === 'manager'
            ? 'Manager'
            : 'Administrator',
);

const openNotification = (notification: {
    id: string;
    action_url: string | null;
}): void => {
    router.patch(
        `/notifications/${notification.id}/read`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (notification.action_url) {
                    router.visit(notification.action_url);
                }
            },
        },
    );
};

const readAllNotifications = (): void => {
    router.patch('/notifications/read-all', {}, { preserveScroll: true });
};
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-[76px] shrink-0 items-center justify-between border-b border-sidebar-border/70 bg-background/88 px-4 backdrop-blur-xl transition-all md:px-6 lg:px-8"
    >
        <div class="flex min-w-0 items-center gap-3">
            <SidebarTrigger
                class="-ml-1 size-10 rounded-xl border border-border/70 bg-card hover:bg-secondary"
            />
            <div class="hidden min-w-0 sm:block">
                <p class="truncate text-sm font-semibold tracking-[-0.02em]">
                    {{ pageTitle }}
                </p>
                <p class="mt-0.5 text-[11px] text-muted-foreground">
                    {{ workspaceLabel }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <label class="relative hidden lg:block">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    type="search"
                    aria-label="Search workspace"
                    placeholder="Search anything..."
                    class="h-10 w-56 rounded-full border border-border/70 bg-card pr-4 pl-10 text-xs shadow-sm transition-all placeholder:text-muted-foreground/70 focus:w-64 focus:border-accent focus:ring-3 focus:ring-accent/10"
                />
            </label>

            <div
                class="hidden h-10 items-center gap-2 rounded-full border border-border/70 bg-card px-3.5 text-xs font-medium text-muted-foreground shadow-sm md:flex"
            >
                <CalendarDays class="size-4 text-accent" />
                {{ today }}
            </div>

            <ThemeToggle />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="relative inline-flex size-10 items-center justify-center rounded-full border border-border/70 bg-card text-muted-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:text-foreground hover:shadow-md"
                        :aria-label="`Notifications${notificationCenter.unread ? `, ${notificationCenter.unread} unread` : ''}`"
                    >
                        <Bell class="size-4.5" />
                        <span
                            v-if="notificationCenter.unread"
                            class="absolute -top-0.5 -right-0.5 flex min-w-4.5 items-center justify-center rounded-full border-2 border-background bg-accent px-1 text-[8px] leading-3.5 font-bold text-white"
                        >
                            {{ Math.min(notificationCenter.unread, 99) }}
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    class="w-[min(23rem,calc(100vw-2rem))] rounded-2xl p-2"
                >
                    <div class="flex items-center justify-between px-2 py-1.5">
                        <DropdownMenuLabel class="p-0 text-xs">
                            Notifications
                        </DropdownMenuLabel>
                        <button
                            v-if="notificationCenter.unread"
                            type="button"
                            class="inline-flex items-center gap-1 text-[9px] font-bold text-accent"
                            @click="readAllNotifications"
                        >
                            <CheckCheck class="size-3" /> Mark all read
                        </button>
                    </div>
                    <DropdownMenuSeparator />
                    <div
                        v-if="notificationCenter.items.length"
                        class="max-h-96 space-y-1 overflow-y-auto"
                    >
                        <button
                            v-for="notification in notificationCenter.items"
                            :key="notification.id"
                            type="button"
                            class="flex w-full gap-3 rounded-xl px-2.5 py-3 text-left transition-colors hover:bg-secondary"
                            @click="openNotification(notification)"
                        >
                            <span
                                class="mt-1 size-2 shrink-0 rounded-full"
                                :class="
                                    notification.read
                                        ? 'bg-border'
                                        : notification.severity === 'warning'
                                          ? 'bg-amber-500'
                                          : notification.severity === 'success'
                                            ? 'bg-emerald-500'
                                            : 'bg-accent'
                                "
                            />
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-[11px] font-semibold"
                                >
                                    {{ notification.title }}
                                </span>
                                <span
                                    class="mt-1 line-clamp-2 block text-[10px] leading-4 text-muted-foreground"
                                >
                                    {{ notification.message }}
                                </span>
                                <span
                                    class="mt-1.5 block text-[9px] text-muted-foreground/70"
                                >
                                    {{ notification.created_at }}
                                </span>
                            </span>
                        </button>
                    </div>
                    <p
                        v-else
                        class="px-4 py-8 text-center text-[11px] text-muted-foreground"
                    >
                        You are all caught up.
                    </p>
                </DropdownMenuContent>
            </DropdownMenu>

            <div
                class="ml-0.5 flex items-center gap-2.5 rounded-full border border-border/70 bg-card py-1 pr-3 pl-1 shadow-sm"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-full bg-[#dce8d7] text-[10px] font-bold text-[#34512e] dark:bg-[#31422e] dark:text-[#c7dfbf]"
                    >{{ getInitials(user.name) }}</span
                >
                <div class="hidden max-w-28 lg:block">
                    <p class="truncate text-[11px] font-semibold">
                        {{ user.name }}
                    </p>
                    <p class="text-[9px] font-medium text-muted-foreground">
                        {{ roleLabel }}
                    </p>
                </div>
            </div>
        </div>
    </header>
</template>
