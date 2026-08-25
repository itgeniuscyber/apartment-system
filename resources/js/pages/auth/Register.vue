<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    ArrowRight,
    ContactRound,
    FileKey2,
    LockKeyhole,
    Mail,
    Phone,
    ShieldCheck,
    Upload,
    UserRound,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{ passwordRules: string }>();

defineOptions({
    layout: {
        title: 'Create your resident account',
        description:
            'Complete your secure resident profile once, then manage rent, documents, notices, and requests in one place.',
        wide: true,
    },
});

const inputClass =
    'h-11 rounded-xl border-border bg-card text-sm shadow-sm transition-all focus-visible:border-accent focus-visible:ring-accent/15';
const iconInputClass = `${inputClass} pl-10`;
const selectClass =
    'h-11 w-full rounded-xl border border-border bg-card px-3 text-sm shadow-sm outline-none transition-all focus:border-accent focus:ring-3 focus:ring-accent/15';
const identityDocumentName = ref('');

const setIdentityDocumentName = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    identityDocumentName.value = input.files?.[0]?.name ?? '';
};
</script>

<template>
    <Head title="Create resident account" />

    <Form
        v-bind="store.form()"
        :reset-on-success="[
            'password',
            'password_confirmation',
            'identity_document',
        ]"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <section class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <div class="mb-4 flex items-center gap-2.5">
                    <span
                        class="flex size-9 items-center justify-center rounded-xl bg-accent/10 text-accent"
                    >
                        <UserRound class="size-4.5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold">Your details</h2>
                        <p class="text-[11px] text-muted-foreground">
                            Used for your resident record and portal login
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="name" class="text-[12px] font-semibold"
                    >Full legal name</Label
                >
                <div class="relative">
                    <UserRound
                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Your full name"
                        :class="iconInputClass"
                    />
                </div>
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" class="text-[12px] font-semibold"
                    >Email address</Label
                >
                <div class="relative">
                    <Mail
                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="you@example.com"
                        :class="iconInputClass"
                    />
                </div>
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone" class="text-[12px] font-semibold"
                    >Phone / WhatsApp</Label
                >
                <div class="relative">
                    <Phone
                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        required
                        autocomplete="tel"
                        placeholder="+256 7..."
                        :class="iconInputClass"
                    />
                </div>
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="alternative_phone" class="text-[12px] font-semibold"
                    >Alternative phone</Label
                >
                <Input
                    id="alternative_phone"
                    name="alternative_phone"
                    type="tel"
                    autocomplete="tel"
                    placeholder="Optional"
                    :class="inputClass"
                />
                <InputError :message="errors.alternative_phone" />
            </div>

            <div class="grid gap-2">
                <Label for="preferred_channel" class="text-[12px] font-semibold"
                    >Preferred contact method</Label
                >
                <select
                    id="preferred_channel"
                    name="preferred_channel"
                    :class="selectClass"
                >
                    <option value="whatsapp">WhatsApp</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                </select>
                <InputError :message="errors.preferred_channel" />
            </div>
        </section>

        <section
            class="rounded-[1.4rem] border border-border/70 bg-muted/25 p-4 sm:p-5"
        >
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    >
                        <FileKey2 class="size-4.5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold">
                            Security identification
                        </h2>
                        <p class="text-[11px] text-muted-foreground">
                            Provide an ID number, a document upload, or both
                        </p>
                    </div>
                </div>
                <span
                    class="hidden items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[9px] font-bold text-emerald-600 sm:inline-flex dark:text-emerald-400"
                >
                    <ShieldCheck class="size-3" /> Private
                </span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label
                        for="identity_document_type"
                        class="text-[12px] font-semibold"
                        >Document type</Label
                    >
                    <select
                        id="identity_document_type"
                        name="identity_document_type"
                        :class="selectClass"
                    >
                        <option value="national_id">National ID</option>
                        <option value="passport">Passport</option>
                        <option value="driving_permit">Driving permit</option>
                        <option value="refugee_id">Refugee ID</option>
                        <option value="other">Other document</option>
                    </select>
                    <InputError :message="errors.identity_document_type" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="identity_document_number"
                        class="text-[12px] font-semibold"
                        >ID / document number</Label
                    >
                    <Input
                        id="identity_document_number"
                        name="identity_document_number"
                        type="text"
                        autocomplete="off"
                        placeholder="e.g. national ID number"
                        :class="inputClass"
                    />
                    <InputError :message="errors.identity_document_number" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label
                        for="identity_document"
                        class="text-[12px] font-semibold"
                        >Upload identity document</Label
                    >
                    <label
                        for="identity_document"
                        class="flex min-h-20 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-border bg-card px-4 py-3 transition-all hover:border-accent/50 hover:bg-accent/[0.025]"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent"
                        >
                            <Upload class="size-4" />
                        </span>
                        <span>
                            <span
                                class="block max-w-sm truncate text-xs font-semibold"
                                >{{
                                    identityDocumentName ||
                                    'Choose a PDF or image'
                                }}</span
                            >
                            <span
                                class="mt-1 block text-[10px] leading-4 text-muted-foreground"
                            >
                                PDF, JPG or PNG · maximum 5 MB · stored
                                privately
                            </span>
                        </span>
                    </label>
                    <input
                        id="identity_document"
                        name="identity_document"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        class="sr-only"
                        @change="setIdentityDocumentName"
                    />
                    <InputError :message="errors.identity_document" />
                </div>
            </div>
        </section>

        <section
            class="rounded-[1.4rem] border border-border/70 bg-card p-4 sm:p-5"
        >
            <div class="mb-4 flex items-center gap-2.5">
                <span
                    class="flex size-9 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                >
                    <ContactRound class="size-4.5" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold">Next of kin</h2>
                    <p class="text-[11px] text-muted-foreground">
                        A trusted contact for emergencies and security
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label
                        for="next_of_kin_name"
                        class="text-[12px] font-semibold"
                        >Full name</Label
                    >
                    <Input
                        id="next_of_kin_name"
                        name="next_of_kin_name"
                        type="text"
                        required
                        autocomplete="off"
                        placeholder="Next of kin's name"
                        :class="inputClass"
                    />
                    <InputError :message="errors.next_of_kin_name" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="next_of_kin_relationship"
                        class="text-[12px] font-semibold"
                        >Relationship</Label
                    >
                    <Input
                        id="next_of_kin_relationship"
                        name="next_of_kin_relationship"
                        type="text"
                        required
                        autocomplete="off"
                        placeholder="e.g. Spouse, parent, sibling"
                        :class="inputClass"
                    />
                    <InputError :message="errors.next_of_kin_relationship" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="next_of_kin_phone"
                        class="text-[12px] font-semibold"
                        >Phone number</Label
                    >
                    <Input
                        id="next_of_kin_phone"
                        name="next_of_kin_phone"
                        type="tel"
                        required
                        autocomplete="off"
                        placeholder="+256 7..."
                        :class="inputClass"
                    />
                    <InputError :message="errors.next_of_kin_phone" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="next_of_kin_email"
                        class="text-[12px] font-semibold"
                        >Email address</Label
                    >
                    <Input
                        id="next_of_kin_email"
                        name="next_of_kin_email"
                        type="email"
                        autocomplete="off"
                        placeholder="Optional"
                        :class="inputClass"
                    />
                    <InputError :message="errors.next_of_kin_email" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label
                        for="next_of_kin_address"
                        class="text-[12px] font-semibold"
                        >Home or work address</Label
                    >
                    <textarea
                        id="next_of_kin_address"
                        name="next_of_kin_address"
                        rows="2"
                        placeholder="Optional"
                        class="w-full rounded-xl border border-border bg-card px-3 py-2.5 text-sm shadow-sm transition-all outline-none focus:border-accent focus:ring-3 focus:ring-accent/15"
                    />
                    <InputError :message="errors.next_of_kin_address" />
                </div>
            </div>
        </section>

        <section>
            <div class="mb-4 flex items-center gap-2.5">
                <span
                    class="flex size-9 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400"
                >
                    <LockKeyhole class="size-4.5" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold">Portal password</h2>
                    <p class="text-[11px] text-muted-foreground">
                        Known only to you and protected by secure hashing
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="password" class="text-[12px] font-semibold"
                        >Password</Label
                    >
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Create a strong password"
                        :passwordrules="passwordRules"
                        class="h-11 rounded-xl border-border bg-card pr-12 text-sm shadow-sm focus-visible:border-accent focus-visible:ring-accent/15"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="password_confirmation"
                        class="text-[12px] font-semibold"
                        >Confirm password</Label
                    >
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Repeat your password"
                        :passwordrules="passwordRules"
                        class="h-11 rounded-xl border-border bg-card pr-12 text-sm shadow-sm focus-visible:border-accent focus-visible:ring-accent/15"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </div>
        </section>

        <Button
            type="submit"
            class="group h-12 w-full rounded-2xl bg-accent text-sm font-semibold text-white shadow-[0_12px_28px_rgba(242,103,69,0.22)] transition-all hover:-translate-y-0.5 hover:bg-accent/90"
            :disabled="processing"
            data-test="register-user-button"
        >
            <Spinner v-if="processing" />
            <template v-else>
                Create secure resident account
                <ArrowRight
                    class="size-4 transition-transform group-hover:translate-x-1"
                />
            </template>
        </Button>

        <div class="text-center text-sm text-muted-foreground">
            Already have access?
            <TextLink
                :href="login()"
                class="ml-1 font-semibold text-foreground no-underline hover:text-accent"
                >Sign in</TextLink
            >
        </div>
    </Form>
</template>
