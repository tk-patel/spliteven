<script setup lang="ts">
import { Form, Head, useHttp } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { checkUsername, login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});

const USERNAME_PATTERN = /^[a-z0-9_]{3,30}$/;

const username = ref('');
const usernameState = ref<'idle' | 'checking' | 'available' | 'taken'>('idle');
const http = useHttp(checkUsername(), { username: '' });

const checkAvailability = useDebounceFn(async () => {
    const value = username.value.trim().toLowerCase();

    if (!USERNAME_PATTERN.test(value)) {
        usernameState.value = 'idle';

        return;
    }

    usernameState.value = 'checking';

    try {
        http.username = value;

        const { available } = (await http.submit()) as { available: boolean };

        usernameState.value = available ? 'available' : 'taken';
    } catch {
        usernameState.value = 'idle';
    }
}, 500);

watch(username, () => {
    usernameState.value = 'idle';

    if (username.value.trim().length >= 3) {
        checkAvailability();
    }
});
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        :preserve-scroll="false"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    type="text"
                    required
                    :tabindex="2"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    name="username"
                    v-model="username"
                    placeholder="e.g. jane_doe"
                />
                <InputError :message="errors.username" />
                <p class="text-xs text-muted-foreground">
                    lowercase letters, numbers, underscores only
                </p>
                <p
                    v-if="usernameState === 'checking'"
                    class="text-xs text-muted-foreground"
                >
                    Checking availability…
                </p>
                <p
                    v-else-if="usernameState === 'available'"
                    class="text-xs font-medium text-green-600"
                >
                    ✓ Username is available
                </p>
                <p
                    v-else-if="usernameState === 'taken'"
                    class="text-xs font-medium text-red-600"
                >
                    This username is already taken
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="3"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="6"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="7"
                >Log in</TextLink
            >
        </div>
    </Form>
</template>
