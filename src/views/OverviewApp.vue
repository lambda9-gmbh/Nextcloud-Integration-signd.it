<!--
  SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
    <NcContent app-name="integration_signd">
        <NcAppContent>
            <div class="signd-overview">
                <h2 class="signd-overview__header">
                    {{ t('integration_signd', 'Signing Processes') }}
                    <NcButton v-if="apiKeySet"
                        variant="tertiary"
                        :aria-label="t('integration_signd', 'Refresh')"
                        :disabled="isLoading"
                        @click="loadProcesses">
                        <template #icon>
                            <NcLoadingIcon v-if="isLoading" :size="20" />
                            <NcIconSvgWrapper v-else :path="mdiRefresh" :size="20" />
                        </template>
                    </NcButton>
                </h2>

                <!-- No API key warning -->
                <NcNoteCard v-if="!apiKeySet" type="warning">
                    {{ t('integration_signd', 'signd.it is not configured. An administrator needs to set up the API key in the admin settings.') }}
                </NcNoteCard>

                <template v-else>
                    <OverviewToolbar
                        :status="status"
                        :search-query="searchQuery"
                        :date-from="dateFrom"
                        :date-to="dateTo"
                        :only-mine="onlyMine"
                        @update:status="onStatusChange"
                        @update:search-query="onSearchChange"
                        @update:date-from="onDateFromChange"
                        @update:date-to="onDateToChange"
                        @update:only-mine="onOnlyMineChange" />

                    <!-- Loading -->
                    <div v-if="isLoading && processes.length === 0" class="signd-overview__loading">
                        <NcLoadingIcon :size="28" />
                    </div>

                    <!-- Error -->
                    <NcNoteCard v-if="error" type="error">
                        {{ error }}
                    </NcNoteCard>

                    <!-- Table -->
                    <OverviewTable
                        v-if="!isLoading || processes.length > 0"
                        :processes="processes"
                        :sort-criteria="sortCriteria"
                        :sort-order="sortOrder"
                        :selected-id="selectedProcess?.documentId ?? ''"
                        @select="onSelectProcess"
                        @update:sort-criteria="onSortCriteriaChange"
                        @update:sort-order="onSortOrderChange" />

                    <!-- Pagination -->
                    <OverviewPagination
                        :offset="offset"
                        :limit="limit"
                        :total="totalHits"
                        @prev="goToPrev"
                        @next="goToNext" />
                </template>
            </div>
        </NcAppContent>

        <!-- Detail Sidebar -->
        <NcAppSidebar
            v-if="selectedProcess"
            :name="selectedProcess.name || t('integration_signd', 'Signing process')"
            @close="selectedProcess = null">
            <ProcessDetail
                :process="selectedProcess"
                @cancelled="onDetailCancelled" />
        </NcAppSidebar>
    </NcContent>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import OverviewToolbar from '../components/overview/OverviewToolbar.vue'
import OverviewTable from '../components/overview/OverviewTable.vue'
import OverviewPagination from '../components/overview/OverviewPagination.vue'
import ProcessDetail from '../components/overview/ProcessDetail.vue'

import { overviewApi, extractErrorMessage } from '../services/api'
import type { FoundProcess } from '../services/api'

export default defineComponent({
    name: 'OverviewApp',

    components: {
        NcButton,
        NcContent,
        NcAppContent,
        NcAppSidebar,
        NcIconSvgWrapper,
        NcNoteCard,
        NcLoadingIcon,
        OverviewToolbar,
        OverviewTable,
        OverviewPagination,
        ProcessDetail,
    },

    data() {
        return {
            // mdi refresh icon path (avoids @mdi/js dependency)
            mdiRefresh: 'M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z',
            apiKeySet: loadState('integration_signd', 'api_key_set', false) as boolean,

            // Filter state
            status: 'ALL',
            searchQuery: '',
            dateFrom: '',
            dateTo: '',
            onlyMine: false,

            // Sort state
            sortCriteria: 'CREATED',
            sortOrder: 'DESC',

            // Pagination
            offset: 0,
            limit: 25,
            totalHits: 0,

            // Data
            processes: [] as FoundProcess[],
            selectedProcess: null as FoundProcess | null,

            // UI
            isLoading: false,
            error: '',
        }
    },

    mounted() {
        if (this.apiKeySet) {
            this.loadProcesses()
        }
    },

    methods: {
        t,

        async loadProcesses() {
            this.isLoading = true
            this.error = ''

            try {
                const result = await overviewApi.list({
                    status: this.status,
                    limit: this.limit,
                    offset: this.offset,
                    searchQuery: this.searchQuery,
                    dateFrom: this.dateFrom,
                    dateTo: this.dateTo,
                    sortCriteria: this.sortCriteria,
                    sortOrder: this.sortOrder,
                    onlyMine: this.onlyMine,
                })

                this.processes = result.processes || []
                this.totalHits = result.numHits || 0

                // Update selected process if it's still in the list
                if (this.selectedProcess) {
                    const updated = this.processes.find(
                        (p) => p.documentId === this.selectedProcess!.documentId,
                    )
                    this.selectedProcess = updated || null
                }
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to load processes.'))
            } finally {
                this.isLoading = false
            }
        },

        resetAndLoad() {
            this.offset = 0
            this.loadProcesses()
        },

        onStatusChange(value: string) {
            this.status = value
            this.resetAndLoad()
        },

        onSearchChange(value: string) {
            this.searchQuery = value
            this.resetAndLoad()
        },

        onDateFromChange(value: string) {
            this.dateFrom = value
            this.resetAndLoad()
        },

        onDateToChange(value: string) {
            this.dateTo = value
            this.resetAndLoad()
        },

        onOnlyMineChange(value: boolean) {
            this.onlyMine = value
            this.resetAndLoad()
        },

        onSortCriteriaChange(value: string) {
            this.sortCriteria = value
            this.resetAndLoad()
        },

        onSortOrderChange(value: string) {
            this.sortOrder = value
            this.resetAndLoad()
        },

        goToPrev() {
            this.offset = Math.max(0, this.offset - this.limit)
            this.loadProcesses()
        },

        goToNext() {
            this.offset += this.limit
            this.loadProcesses()
        },

        onSelectProcess(process: FoundProcess) {
            this.selectedProcess = process
        },

        onDetailCancelled() {
            this.selectedProcess = null
            this.loadProcesses()
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-overview {
    padding: 20px;

    &__header {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 8px;
        font-size: 20px;
        font-weight: 600;
    }

    &__loading {
        display: flex;
        justify-content: center;
        padding: 40px;
    }
}
</style>
