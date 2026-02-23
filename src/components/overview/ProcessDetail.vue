<template>
    <div class="signd-process-detail">
        <div class="signd-process-detail__header">
            <h3>{{ process.name || t('integration_signd', 'Signing process') }}</h3>
            <span :class="['signd-badge', `signd-badge--${statusClass}`]">
                {{ statusLabel }}
            </span>
        </div>

        <div class="signd-process-detail__info">
            <div class="signd-process-detail__field">
                <strong>{{ t('integration_signd', 'File:') }}</strong>
                <a
                    v-if="fileId"
                    :href="fileLink"
                    class="signd-file-link">
                    {{ fileName }}
                </a>
                <span v-else :class="{ 'signd-file-link--missing': hasFileMetadata }">
                    {{ fileName }}
                </span>
            </div>

            <div class="signd-process-detail__field">
                <strong>{{ t('integration_signd', 'Initiator:') }}</strong>
                {{ initiator }}
            </div>

            <div class="signd-process-detail__field">
                <strong>{{ t('integration_signd', 'Created:') }}</strong>
                {{ formatDate(process.created) }}
            </div>

            <div class="signd-process-detail__field">
                <strong>{{ t('integration_signd', 'Progress:') }}</strong>
                {{ progress }}
            </div>
        </div>

        <!-- Signer lists -->
        <div class="signd-process-detail__signers">
            <SignerList
                :label="t('integration_signd', 'Signed:')"
                :signers="process.signersCompleted || []" />

            <SignerList
                :label="t('integration_signd', 'Rejected:')"
                :signers="process.signersRejected || []"
                variant="rejected" />

            <SignerList
                :label="t('integration_signd', 'Pending:')"
                :signers="process.signersPending || []"
                variant="pending" />
        </div>

        <!-- Actions -->
        <div class="signd-process-detail__actions">
            <NcButton variant="secondary" :disabled="isRefreshing" @click="onRefresh">
                <template #icon>
                    <NcLoadingIcon v-if="isRefreshing" :size="20" />
                </template>
                {{ t('integration_signd', 'Refresh') }}
            </NcButton>

            <NcButton
                v-if="canDownload"
                variant="primary"
                @click="onDownload">
                {{ t('integration_signd', 'Download signed PDF') }}
            </NcButton>

            <NcButton
                v-if="canCancel"
                variant="error"
                :disabled="isCancelling"
                @click="onCancel">
                <template #icon>
                    <NcLoadingIcon v-if="isCancelling" :size="20" />
                </template>
                {{ t('integration_signd', 'Cancel process') }}
            </NcButton>
        </div>

        <NcNoteCard v-if="error" type="error">
            {{ error }}
        </NcNoteCard>

        <NcNoteCard v-if="successMessage" type="success">
            {{ successMessage }}
        </NcNoteCard>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import SignerList from '../SignerList.vue'
import { overviewApi, processApi, extractErrorMessage } from '../../services/api'
import { notifyFileCreated } from '../../services/fileListNotify'
import type { FoundProcess } from '../../services/api'

export default defineComponent({
    name: 'ProcessDetail',

    components: {
        NcButton,
        NcNoteCard,
        NcLoadingIcon,
        SignerList,
    },

    props: {
        process: {
            type: Object as PropType<FoundProcess>,
            required: true,
        },
    },

    emits: ['refresh', 'cancelled'],

    data() {
        return {
            isRefreshing: false,
            isCancelling: false,
            error: '',
            successMessage: '',
        }
    },

    computed: {
        statusClass(): string {
            if (this.process.cancelled) return 'error'
            const pending = this.process.signersPending || []
            if (pending.length === 0 && (this.process.signersCompleted?.length || 0) > 0) return 'success'
            return 'pending'
        },

        statusLabel(): string {
            if (this.process.cancelled) return t('integration_signd', 'Cancelled')
            const pending = this.process.signersPending || []
            if (pending.length === 0 && (this.process.signersCompleted?.length || 0) > 0) return t('integration_signd', 'Completed')
            return t('integration_signd', 'Pending')
        },

        fileId(): string {
            const meta = this.process.apiClientMetaData?.applicationMetaData
            if (!meta?.ncFileId || meta._ncFileExists === false) return ''
            return meta.ncFileId
        },

        hasFileMetadata(): boolean {
            return !!this.process.apiClientMetaData?.applicationMetaData?.ncFileId
        },

        fileName(): string {
            return this.process.apiClientMetaData?.applicationMetaData?.ncFileName
                || this.process.filename
                || '—'
        },

        fileLink(): string {
            if (!this.fileId) return ''
            return generateUrl('/apps/files/?fileid={fileId}', { fileId: this.fileId })
        },

        initiator(): string {
            return this.process.apiClientMetaData?.applicationMetaData?.ncUserId || '—'
        },

        progress(): string {
            const completed = this.process.signersCompleted?.length || 0
            const rejected = this.process.signersRejected?.length || 0
            const pending = this.process.signersPending?.length || 0
            const total = completed + rejected + pending
            if (total === 0) return '—'
            return `${completed}/${total}`
        },

        canDownload(): boolean {
            // Process is finished (no pending signers, has completed signers, not cancelled)
            if (this.process.cancelled) return false
            const pending = this.process.signersPending || []
            const completed = this.process.signersCompleted || []
            return pending.length === 0 && completed.length > 0
        },

        canCancel(): boolean {
            // Can cancel if not already cancelled/finished
            if (this.process.cancelled) return false
            const pending = this.process.signersPending || []
            return pending.length > 0
        },
    },

    methods: {
        t,

        formatDate(dateStr?: string): string {
            if (!dateStr) return '—'
            try {
                return new Date(dateStr).toLocaleString()
            } catch {
                return dateStr
            }
        },

        async onRefresh() {
            this.isRefreshing = true
            this.error = ''
            this.successMessage = ''

            try {
                await processApi.refresh(this.process.processId)
                this.$emit('refresh')
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to refresh process status.'))
            } finally {
                this.isRefreshing = false
            }
        },

        async onDownload() {
            this.error = ''
            this.successMessage = ''

            try {
                const result = await processApi.download(this.process.processId, this.process.filename)
                notifyFileCreated(result)
                if (result.targetDirMissing) {
                    this.successMessage = t('integration_signd', 'Original folder no longer exists. Signed PDF was saved to your home folder.')
                } else {
                    this.successMessage = t('integration_signd', 'Signed PDF saved.')
                }
                this.$emit('refresh')
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to download signed PDF.'))
            }
        },

        async onCancel() {
            this.isCancelling = true
            this.error = ''
            this.successMessage = ''

            try {
                await overviewApi.cancel(this.process.processId)
                this.successMessage = t('integration_signd', 'Process cancelled.')
                this.$emit('cancelled')
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to cancel process.'))
            } finally {
                this.isCancelling = false
            }
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-process-detail {
    padding: 12px;

    &__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;

        h3 {
            margin: 0;
            font-size: 16px;
        }
    }

    &__info {
        margin-bottom: 16px;
    }

    &__field {
        margin-bottom: 6px;
        font-size: 14px;

        strong {
            margin-right: 4px;
        }
    }

    &__signers {
        margin-bottom: 16px;
    }

    &__actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }
}

.signd-badge {
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;

    &--success {
        background: var(--color-success);
        color: white;
    }

    &--error {
        background: var(--color-error);
        color: white;
    }

    &--pending {
        background: var(--color-warning);
        color: var(--color-warning-text);
    }
}

.signd-file-link {
    color: var(--color-primary-element);
    text-decoration: none;

    &:hover {
        text-decoration: underline;
    }

    &--missing {
        color: var(--color-text-maxcontrast);
    }
}
</style>
