<template>
    <div class="signd-process-status">
        <div class="signd-process-header">
            <span class="signd-process-name">
                {{ processName }}
            </span>
            <span :class="['signd-badge', `signd-badge--${statusClass}`]">
                {{ statusLabel }}
            </span>
        </div>

        <div class="signd-process-date">
            {{ t('integration_signd', 'Created: {date}', { date: formatDate(process.meta?.created) }) }}
        </div>

        <!-- Signer details from meta -->
        <template v-if="process.meta">
            <SignerList
                :label="t('integration_signd', 'Signed:')"
                :signers="process.meta.signersCompleted" />

            <SignerList
                :label="t('integration_signd', 'Rejected:')"
                :signers="process.meta.signersRejected"
                variant="rejected" />

            <SignerList
                :label="t('integration_signd', 'Pending:')"
                :signers="process.meta.signersPending"
                variant="pending" />
        </template>

        <!-- Actions: Draft -->
        <div v-if="derivedStatus === 'DRAFT'" class="signd-process-actions">
            <NcButton variant="primary" @click="$emit('resume-wizard')">
                {{ t('integration_signd', 'Resume wizard') }}
            </NcButton>
            <NcButton variant="tertiary" @click="$emit('cancel-wizard')">
                {{ t('integration_signd', 'Cancel draft') }}
            </NcButton>
        </div>

        <!-- Actions: Normal process -->
        <template v-else>
            <NcNoteCard v-if="process.finishedPdfDeleted" type="warning">
                {{ t('integration_signd', 'Previously saved PDF was deleted.') }}
            </NcNoteCard>
            <div class="signd-process-actions">
                <NcButton
                    v-if="derivedStatus === 'FINISHED' && !process.finishedPdfPath"
                    variant="primary"
                    @click="$emit('download')">
                    {{ t('integration_signd', 'Download signed PDF') }}
                </NcButton>
                <a v-if="process.finishedPdfFileId" :href="signedPdfLink" class="signd-pdf-link">
                    {{ t('integration_signd', 'Open signed PDF') }}
                </a>
            </div>
        </template>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import SignerList from './SignerList.vue'
import type { SigndProcess } from '../services/api'

export default defineComponent({
    name: 'ProcessStatus',

    components: {
        NcButton,
        NcNoteCard,
        SignerList,
    },

    props: {
        process: {
            type: Object as PropType<SigndProcess>,
            required: true,
        },
    },

    emits: ['download', 'resume-wizard', 'cancel-wizard'],

    computed: {
        processName(): string {
            return this.process.meta?.name
                || this.process.meta?.filename
                || t('integration_signd', 'Signing process')
        },

        derivedStatus(): string {
            if (this.process.isDraft) {
                return 'DRAFT'
            }
            if (!this.process.meta) {
                return 'UNKNOWN'
            }
            if (this.process.meta.cancelled) {
                return 'CANCELLED'
            }
            if (!this.process.meta.signersPending?.length) {
                return 'FINISHED'
            }
            return 'PENDING'
        },

        statusClass(): string {
            switch (this.derivedStatus) {
            case 'FINISHED':
                return 'success'
            case 'CANCELLED':
                return 'error'
            case 'DRAFT':
                return 'info'
            case 'UNKNOWN':
                return 'unknown'
            default:
                return 'pending'
            }
        },

        signedPdfLink(): string {
            return generateUrl(`/apps/files/?fileid=${this.process.finishedPdfFileId}`)
        },

        statusLabel(): string {
            switch (this.derivedStatus) {
            case 'FINISHED':
                return t('integration_signd', 'Completed')
            case 'CANCELLED':
                return t('integration_signd', 'Cancelled')
            case 'DRAFT':
                return t('integration_signd', 'Draft')
            case 'UNKNOWN':
                return t('integration_signd', 'Unknown')
            default:
                return t('integration_signd', 'Pending')
            }
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
    },
})
</script>

<style lang="scss" scoped>
.signd-process-status {
    background: var(--color-background-dark);
    border-radius: var(--border-radius-large);
    padding: 12px;

    .signd-process-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }

    .signd-process-name {
        font-weight: bold;
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

        &--info {
            background: var(--color-primary-element);
            color: var(--color-primary-element-text);
        }

        &--unknown {
            background: var(--color-text-maxcontrast);
            color: white;
        }
    }

    .signd-process-date {
        font-size: 12px;
        color: var(--color-text-maxcontrast);
        margin-bottom: 8px;
    }

    .signd-process-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-top: 8px;
    }

    .signd-pdf-link {
        font-size: 12px;
        color: var(--color-primary-element);
        text-decoration: none;

        &:hover {
            text-decoration: underline;
        }
    }

}
</style>
