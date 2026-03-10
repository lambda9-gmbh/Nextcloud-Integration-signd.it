<!--
  SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
    <div class="signd-start-process">
        <NcButton
            variant="primary"
            wide
            :disabled="isStarting"
            @click="openDialog">
            <template #icon>
                <NcLoadingIcon v-if="isStarting" :size="20" />
            </template>
            {{ t('integration_signd', 'Start signing process') }}
        </NcButton>

        <NcNoteCard v-if="error" type="error">
            {{ error }}
        </NcNoteCard>

        <NcDialog
            v-if="showDialog"
            :name="t('integration_signd', 'Start signing process')"
            @closing="closeDialog">
            <p class="signd-dialog-filename">
                {{ fileName }}
            </p>

            <NcCheckboxRadioSwitch
                :model-value="notifyInitiator"
                @update:model-value="notifyInitiator = $event">
                {{ t('integration_signd', 'Notify me about status changes via email') }}
            </NcCheckboxRadioSwitch>

            <NcTextField
                v-if="notifyInitiator"
                v-model="initiatorEmail"
                :label="t('integration_signd', 'Notification email')"
                :error="!!emailError"
                :helper-text="emailError"
                type="email"
                class="signd-dialog-email" />

            <NcNoteCard v-if="dialogError" type="error">
                {{ dialogError }}
            </NcNoteCard>

            <template #actions>
                <NcButton variant="tertiary" :disabled="isStarting" @click="closeDialog">
                    {{ t('integration_signd', 'Cancel') }}
                </NcButton>
                <NcButton variant="primary" :disabled="isStarting || !canStart" @click="startProcess">
                    <template #icon>
                        <NcLoadingIcon v-if="isStarting" :size="20" />
                    </template>
                    {{ t('integration_signd', 'Start') }}
                </NcButton>
            </template>
        </NcDialog>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { processApi, extractErrorMessage } from '../services/api'

export default defineComponent({
    name: 'StartProcessButton',

    components: {
        NcButton,
        NcNoteCard,
        NcLoadingIcon,
        NcDialog,
        NcCheckboxRadioSwitch,
        NcTextField,
    },

    props: {
        fileId: {
            type: Number,
            required: true,
        },
        fileName: {
            type: String,
            default: '',
        },
    },

    emits: ['started'],

    computed: {
        emailError(): string {
            if (!this.notifyInitiator) return ''
            if (!this.initiatorEmail.trim()) {
                return t('integration_signd', 'Please enter an email address.')
            }
            if (!this.isValidEmail(this.initiatorEmail.trim())) {
                return t('integration_signd', 'Please enter a valid email address.')
            }
            return ''
        },

        canStart(): boolean {
            return !this.notifyInitiator || !this.emailError
        },
    },

    data() {
        return {
            isStarting: false,
            error: '',
            showDialog: false,
            dialogError: '',
            notifyInitiator: true,
            initiatorEmail: '',
            isLoadingUser: false,
        }
    },

    methods: {
        t,

        async openDialog() {
            this.showDialog = true
            this.dialogError = ''
            this.notifyInitiator = true
            this.initiatorEmail = ''

            this.isLoadingUser = true
            try {
                const user = await processApi.getCurrentUser()
                this.initiatorEmail = user.email
            } catch (e) {
                this.dialogError = extractErrorMessage(e, t('integration_signd', 'Failed to load user information.'))
            } finally {
                this.isLoadingUser = false
            }
        },

        closeDialog() {
            this.showDialog = false
            this.dialogError = ''
        },

        isValidEmail(email: string): boolean {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
        },

        async startProcess() {
            this.isStarting = true
            this.error = ''
            this.dialogError = ''

            try {
                const result = await processApi.startWizard({
                    fileId: this.fileId,
                    notifyInitiator: this.notifyInitiator,
                    initiatorEmail: this.notifyInitiator ? this.initiatorEmail : undefined,
                })

                this.showDialog = false

                // Open wizard URL in new tab
                if (result.wizardUrl) {
                    window.open(result.wizardUrl, '_blank')
                }

                this.$emit('started', result)
            } catch (e) {
                this.dialogError = extractErrorMessage(e, t('integration_signd', 'Failed to start signing process. Please try again.'))
            } finally {
                this.isStarting = false
            }
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-start-process {
    margin-top: 16px;
}

.signd-dialog-filename {
    font-weight: bold;
    margin-bottom: 12px;
}

.signd-dialog-email {
    margin-top: 24px;
    margin-bottom: 16px;
}
</style>
