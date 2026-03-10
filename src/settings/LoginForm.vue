<!--
  SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
    <div class="signd-login-form">
        <h3>{{ t('integration_signd', 'Login with signd.it credentials') }}</h3>
        <p class="signd-description">
            {{ t('integration_signd', 'Log in with your signd.it account to automatically configure the API key.') }}
        </p>

        <div class="signd-form-field">
            <NcTextField
                v-model="email"
                :label="t('integration_signd', 'Email')"
                type="email"
                :disabled="isLoading" />
        </div>

        <div class="signd-form-field">
            <NcPasswordField
                v-model="password"
                :label="t('integration_signd', 'Password')"
                :disabled="isLoading" />
        </div>

        <NcNoteCard v-if="error" type="error">
            {{ error }}
        </NcNoteCard>

        <NcButton
            variant="primary"
            :disabled="!email || !password || isLoading"
            @click="login">
            <template #icon>
                <NcLoadingIcon v-if="isLoading" :size="20" />
            </template>
            {{ t('integration_signd', 'Login') }}
        </NcButton>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import { settingsApi } from '../services/api'

export default defineComponent({
    name: 'LoginForm',

    components: {
        NcButton,
        NcTextField,
        NcPasswordField,
        NcNoteCard,
        NcLoadingIcon,
    },

    emits: ['logged-in'],

    data() {
        return {
            email: '',
            password: '',
            isLoading: false,
            error: '',
        }
    },

    methods: {
        t,

        async login() {
            this.isLoading = true
            this.error = ''

            try {
                const result = await settingsApi.login(this.email, this.password)
                this.email = ''
                this.password = ''
                this.$emit('logged-in', result)
            } catch (e: unknown) {
                const error = e as { response?: { data?: { error?: string, errorCode?: string } } }
                if (error.response?.data?.errorCode === 'SIGND_UNREACHABLE') {
                    this.error = t('integration_signd', 'Cannot reach the signd.it server. Please try again later.')
                } else {
                    this.error = error.response?.data?.error || t('integration_signd', 'Login failed. Please check your credentials.')
                }
            } finally {
                this.isLoading = false
            }
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-login-form {
    .signd-description {
        color: var(--color-text-maxcontrast);
        margin-bottom: 12px;
    }

    .signd-form-field {
        margin-bottom: 12px;
        max-width: 400px;
    }
}
</style>
