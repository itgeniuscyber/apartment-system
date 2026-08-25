<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowRight, LockKeyhole, Mail } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Welcome back',
        description:
            'Sign in to manage your properties, rent collections, residents, and service requests.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Sign in" />

    <div
        v-if="status"
        class="mb-5 rounded-2xl border border-emerald-500/15 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-300"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2.5">
                <Label for="email" class="text-[13px] font-semibold"
                    >Email address</Label
                >
                <div class="relative">
                    <Mail
                        class="pointer-events-none absolute top-1/2 left-4 z-10 size-4.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="you@krestates.com"
                        class="h-12 rounded-2xl border-border bg-card pl-11 text-sm shadow-sm transition-all focus-visible:border-accent focus-visible:ring-accent/15"
                    />
                </div>
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2.5">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-[13px] font-semibold"
                        >Password</Label
                    >
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs font-semibold text-accent no-underline hover:text-accent/80"
                        :tabindex="5"
                        >Forgot password?</TextLink
                    >
                </div>
                <div class="relative">
                    <LockKeyhole
                        class="pointer-events-none absolute top-1/2 left-4 z-10 size-4.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        class="h-12 rounded-2xl border-border bg-card pr-12 pl-11 text-sm shadow-sm transition-all focus-visible:border-accent focus-visible:ring-accent/15"
                    />
                </div>
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex cursor-pointer items-center gap-3 text-[13px] font-medium text-muted-foreground"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="rounded-md data-[state=checked]:border-accent data-[state=checked]:bg-accent"
                    />
                    <span>Keep me signed in</span>
                </Label>
                <span
                    class="inline-flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground"
                >
                    <span class="size-1.5 rounded-full bg-emerald-500" /> Secure
                    access
                </span>
            </div>

            <Button
                type="submit"
                class="group mt-1 h-12 w-full rounded-2xl bg-accent text-sm font-semibold text-white shadow-[0_12px_28px_rgba(242,103,69,0.22)] transition-all hover:-translate-y-0.5 hover:bg-accent/90 hover:shadow-[0_16px_34px_rgba(242,103,69,0.28)]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                <template v-else
                    >Sign in to dashboard
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1"
                /></template>
            </Button>
        </div>
    </Form>

    <PasskeyVerify class="mt-6" label="Sign in with a passkey" />

    <div class="mt-7 text-center text-sm text-muted-foreground">
        New to the property portal?
        <TextLink
            :href="register()"
            :tabindex="6"
            class="ml-1 font-semibold text-foreground no-underline hover:text-accent"
            >Create account</TextLink
        >
    </div>
</template>
