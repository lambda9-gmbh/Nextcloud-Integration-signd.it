<template>
    <div class="signd-overview-table-wrapper">
        <table class="signd-overview-table">
            <thead>
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        :class="{ 'signd-sortable': col.sortable, 'signd-sorted': sortCriteria === col.sortKey }"
                        @click="col.sortable ? toggleSort(col.sortKey!) : undefined">
                        {{ col.label }}
                        <span v-if="col.sortable && sortCriteria === col.sortKey" class="signd-sort-indicator">
                            {{ sortOrder === 'ASC' ? '\u25B2' : '\u25BC' }}
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="processes.length === 0">
                    <td :colspan="columns.length" class="signd-overview-table__empty">
                        {{ t('integration_signd', 'No processes found.') }}
                    </td>
                </tr>
                <tr
                    v-for="process in processes"
                    :key="process.documentId"
                    class="signd-overview-table__row"
                    :class="{ 'signd-overview-table__row--selected': selectedId === process.documentId }"
                    @click="$emit('select', process)">
                    <td>{{ process.name || '—' }}</td>
                    <td>
                        <a
                            v-if="getFileId(process)"
                            :href="getFileLink(process)"
                            class="signd-file-link"
                            @click.stop>
                            {{ getFileName(process) }}
                        </a>
                        <span v-else :class="{ 'signd-file-link--missing': hasFileMetadata(process) }">
                            {{ getFileName(process) }}
                        </span>
                    </td>
                    <td>{{ getInitiator(process) }}</td>
                    <td>
                        <span :class="['signd-badge', `signd-badge--${getStatusClass(process)}`]">
                            {{ getStatusLabel(process) }}
                        </span>
                    </td>
                    <td>{{ formatDate(process.created) }}</td>
                    <td class="signd-progress" v-html="getProgress(process)" />
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import type { FoundProcess } from '../../services/api'

interface Column {
    key: string
    label: string
    sortable: boolean
    sortKey?: string
}

export default defineComponent({
    name: 'OverviewTable',

    props: {
        processes: {
            type: Array as PropType<FoundProcess[]>,
            required: true,
        },
        sortCriteria: {
            type: String,
            default: '',
        },
        sortOrder: {
            type: String,
            default: 'DESC',
        },
        selectedId: {
            type: String,
            default: '',
        },
    },

    emits: ['select', 'update:sortCriteria', 'update:sortOrder'],

    data() {
        return {
            columns: [
                { key: 'name', label: t('integration_signd', 'Process name'), sortable: true, sortKey: 'NAME' },
                { key: 'filename', label: t('integration_signd', 'File'), sortable: false },
                { key: 'initiator', label: t('integration_signd', 'Initiator'), sortable: false },
                { key: 'status', label: t('integration_signd', 'Status'), sortable: true, sortKey: 'STATUS' },
                { key: 'created', label: t('integration_signd', 'Created'), sortable: true, sortKey: 'CREATED' },
                { key: 'progress', label: t('integration_signd', 'Progress'), sortable: false },
            ] as Column[],
        }
    },

    methods: {
        t,

        toggleSort(sortKey: string) {
            if (this.sortCriteria === sortKey) {
                this.$emit('update:sortOrder', this.sortOrder === 'ASC' ? 'DESC' : 'ASC')
            } else {
                this.$emit('update:sortCriteria', sortKey)
                this.$emit('update:sortOrder', 'DESC')
            }
        },

        getFileId(process: FoundProcess): string {
            const meta = process.apiClientMetaData?.applicationMetaData
            if (!meta?.ncFileId || meta._ncFileExists === false) return ''
            return meta.ncFileId
        },

        hasFileMetadata(process: FoundProcess): boolean {
            return !!process.apiClientMetaData?.applicationMetaData?.ncFileId
        },

        getFileName(process: FoundProcess): string {
            return process.apiClientMetaData?.applicationMetaData?.ncFileName
                || process.filename
                || '—'
        },

        getFileLink(process: FoundProcess): string {
            const fileId = this.getFileId(process)
            if (!fileId) return ''
            return generateUrl('/apps/files/files/{fileId}', { fileId })
        },

        getInitiator(process: FoundProcess): string {
            return process.apiClientMetaData?.applicationMetaData?.ncUserId || '—'
        },

        getStatusClass(process: FoundProcess): string {
            if (process.cancelled) return 'error'
            const pending = process.signersPending || []
            const completed = process.signersCompleted || []
            const rejected = process.signersRejected || []
            if (pending.length === 0 && (completed.length > 0 || rejected.length > 0)) return 'success'
            return 'pending'
        },

        getStatusLabel(process: FoundProcess): string {
            if (process.cancelled) return t('integration_signd', 'Cancelled')
            const pending = process.signersPending || []
            const completed = process.signersCompleted || []
            const rejected = process.signersRejected || []
            if (pending.length === 0 && (completed.length > 0 || rejected.length > 0)) return t('integration_signd', 'Completed')
            return t('integration_signd', 'Pending')
        },

        getProgress(process: FoundProcess): string {
            const completed = process.signersCompleted?.length || 0
            const rejected = process.signersRejected?.length || 0
            const pending = process.signersPending?.length || 0
            const total = completed + rejected + pending
            if (total === 0) return '—'
            return `<span class="signd-progress__completed">${completed}</span> / <span class="signd-progress__rejected">${rejected}</span> / ${total}`
        },

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
.signd-overview-table-wrapper {
    overflow-x: auto;
}

.signd-overview-table {
    width: 100%;
    border-collapse: collapse;

    th, td {
        padding: 8px 12px;
        text-align: left;
        border-bottom: 1px solid var(--color-border);
    }

    th {
        font-weight: 600;
        color: var(--color-text-maxcontrast);
        font-size: 13px;
        user-select: none;
        white-space: nowrap;
    }

    .signd-sortable {
        cursor: pointer;

        &:hover {
            color: var(--color-main-text);
        }
    }

    .signd-sort-indicator {
        font-size: 10px;
        margin-left: 4px;
    }

    &__empty {
        text-align: center;
        color: var(--color-text-maxcontrast);
        padding: 40px 12px;
    }

    &__row {
        cursor: pointer;

        &:hover {
            background: var(--color-background-hover);
        }

        &--selected {
            background: var(--color-primary-element-light);
        }
    }
}

.signd-badge {
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
    white-space: nowrap;

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

.signd-progress {
    :deep(.signd-progress__completed) {
        color: var(--color-success-text);
        font-weight: 600;
    }

    :deep(.signd-progress__rejected) {
        color: var(--color-error-text);
        font-weight: 600;
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
