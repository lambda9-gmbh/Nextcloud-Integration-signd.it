<!--
  SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
    <div class="signd-apikey-form">
        <h3>{{ t('integration_signd', 'Enter API Key') }}</h3>
        <p class="signd-description">
            {{ t('integration_signd', 'Enter your signd.it API key. You can find it in your signd.it account settings.') }}
        </p>

        <div class="signd-form-field">
            <NcPasswordField
                v-model="apiKey"
                :label="t('integration_signd', 'API Key')"
                :placeholder="t('integration_signd', 'Enter your API key...')"
                :disabled="isSaving" />
        </div>

        <NcNoteCard v-if="error" type="error">
            {{ error }}
        </NcNoteCard>

        <NcButton
            variant="primary"
            :disabled="!apiKey || isSaving"
            @click="save">
            <template #icon>
                <NcLoadingIcon v-if="isSaving" :size="20" />
            </template>
            {{ t('integration_signd', 'Save') }}
        </NcButton>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import { settingsApi } from '../services/api'

export default defineComponent({
    name: 'ApiKeyForm',

    components: {
        NcButton,
        NcPasswordField,
        NcNoteCard,
        NcLoadingIcon,
    },

    emits: ['saved'],

    data() {
        return {
            apiKey: '',
            isSaving: false,
            error: '',
        }
    },

    methods: {
        t,

        async save() {
            this.isSaving = true
            this.error = ''

            try {
                const result = await settingsApi.saveApiKey(this.apiKey)
                this.apiKey = ''
                this.$emit('saved', result)
            } catch (e: unknown) {
                const error = e as { response?: { data?: { error?: string, errorCode?: string } } }
                if (error.response?.data?.errorCode === 'SIGND_UNREACHABLE') {
                    this.error = t('integration_signd', 'Cannot reach the signd.it server. Please try again later.')
                } else {
                    this.error = t('integration_signd', 'Invalid API key. Please check the key and try again.')
                }
            } finally {
                this.isSaving = false
            }
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-apikey-form {
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
