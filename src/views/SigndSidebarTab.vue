<!--
  SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
    <div class="signd-sidebar-tab">
        <!-- No API key configured -->
        <NcNoteCard v-if="!apiKeySet" type="warning">
            {{ t('integration_signd', 'signd.it is not configured. An administrator needs to set up the API key in the admin settings.') }}
        </NcNoteCard>

        <template v-else>
            <!-- Loading -->
            <div v-if="isLoading" class="signd-loading">
                <NcLoadingIcon :size="28" />
            </div>

            <template v-else>
                <!-- Error -->
                <NcNoteCard v-if="error" type="error">
                    {{ error }}
                </NcNoteCard>

                <!-- Warning -->
                <NcNoteCard v-if="warning" type="warning">
                    {{ warning }}
                </NcNoteCard>

                <!-- Start new process button -->
                <div class="signd-start">
                    <StartProcessButton
                        :file-id="fileId"
                        :file-name="fileName"
                        @started="onProcessStarted" />
                </div>

                <!-- Process list with header -->
                <div v-if="processes.length > 0" class="signd-processes-header">
                    <strong>{{ t('integration_signd', 'Existing processes') }}</strong>
                    <NcButton
                        variant="tertiary"
                        :aria-label="t('integration_signd', 'Refresh')"
                        :disabled="isRefreshing"
                        @click="loadProcesses">
                        <template #icon>
                            <NcLoadingIcon v-if="isRefreshing" :size="20" />
                            <NcIconSvgWrapper v-else :path="mdiRefresh" :size="20" />
                        </template>
                    </NcButton>
                </div>

                <div v-if="processes.length > 0 && hasOtherUsers" class="signd-filter">
                    <NcCheckboxRadioSwitch
                        :model-value="onlyMine"
                        @update:model-value="onlyMine = $event">
                        {{ t('integration_signd', 'Only mine') }}
                    </NcCheckboxRadioSwitch>
                </div>

                <ProcessList
                    v-if="processes.length > 0"
                    :processes="filteredProcesses"
                    @download="downloadPdf"
                    @resume-wizard="resumeWizard"
                    @cancel-wizard="cancelWizard" />

                <!-- No processes -->
                <div v-else class="signd-empty">
                    <p>{{ t('integration_signd', 'No signing processes for this document.') }}</p>
                </div>

                <!-- Link to overview page -->
                <div class="signd-overview-link">
                    <a :href="overviewUrl">
                        {{ t('integration_signd', 'Show all processes') }}
                    </a>
                </div>
            </template>
        </template>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

import { getCurrentUser } from '@nextcloud/auth'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import ProcessList from '../components/ProcessList.vue'
import StartProcessButton from '../components/StartProcessButton.vue'

import { processApi, extractErrorMessage } from '../services/api'
import { notifyFileCreated } from '../services/fileListNotify'
import type { SigndProcess } from '../services/api'

export default defineComponent({
    name: 'SigndSidebarTab',

    components: {
        NcButton,
        NcCheckboxRadioSwitch,
        NcNoteCard,
        NcLoadingIcon,
        NcIconSvgWrapper,
        ProcessList,
        StartProcessButton,
    },

    props: {
        node: {
            type: Object,
            default: null,
        },
        folder: {
            type: Object,
            default: null,
        },
        view: {
            type: Object,
            default: null,
        },
        active: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            apiKeySet: loadState('integration_signd', 'api_key_set', false) as boolean,
            processes: [] as SigndProcess[],
            isLoading: false,
            isRefreshing: false,
            error: '',
            warning: '',
            onlyMine: false,
            currentUserId: getCurrentUser()?.uid ?? '',
            mdiRefresh: 'M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z',
        }
    },

    computed: {
        fileId(): number {
            return this.node?.fileid ?? 0
        },

        fileName(): string {
            return this.node?.basename ?? ''
        },

        hasOtherUsers(): boolean {
            return this.processes.some(p => p.userId !== this.currentUserId)
        },

        filteredProcesses(): SigndProcess[] {
            if (!this.onlyMine) return this.processes
            return this.processes.filter(p => p.userId === this.currentUserId)
        },

        overviewUrl(): string {
            return generateUrl('/apps/integration_signd/')
        },
    },

    watch: {
        fileId: {
            immediate: true,
            handler() {
                if (this.fileId && this.apiKeySet) {
                    this.loadProcesses()
                }
            },
        },
    },

    methods: {
        t,

        async loadProcesses() {
            if (!this.fileId) return

            this.isLoading = this.processes.length === 0
            this.isRefreshing = this.processes.length > 0
            this.error = ''

            try {
                this.processes = await processApi.getByFileId(this.fileId)
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to load signing processes.'))
            } finally {
                this.isLoading = false
                this.isRefreshing = false
            }
        },

        async downloadPdf(processId: string) {
            this.warning = ''
            try {
                const process = this.processes.find(p => p.processId === processId)
                const filename = process?.meta?.filename
                const result = await processApi.download(processId, filename)
                notifyFileCreated(result)
                if (result.targetDirMissing) {
                    this.warning = t('integration_signd', 'Original folder no longer exists. Finished PDF was saved to your home folder.')
                }
                await this.loadProcesses()
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to save finished PDF.'))
            }
        },

        async resumeWizard(processId: string) {
            try {
                const result = await processApi.resumeWizard(processId)
                if (result.wizardUrl) {
                    window.open(result.wizardUrl, '_blank')
                }
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to resume wizard.'))
            }
        },

        async cancelWizard(processId: string) {
            try {
                await processApi.cancelWizard(processId)
                await this.loadProcesses()
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to cancel draft.'))
            }
        },

        onProcessStarted() {
            this.loadProcesses()
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-sidebar-tab {
    padding: 10px;

    .signd-loading {
        display: flex;
        justify-content: center;
        padding: 20px;
    }

    .signd-empty {
        text-align: center;
        color: var(--color-text-maxcontrast);
        padding: 20px 0;
    }

    .signd-start {
        display: flex;
        justify-content: center;
        margin-bottom: 12px;
    }

    .signd-processes-header {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 4px;
    }

    .signd-filter {
        margin-bottom: 8px;
    }

    .signd-overview-link {
        text-align: center;
        margin-top: 12px;

        a {
            color: var(--color-primary-element);
            text-decoration: none;

            &:hover {
                text-decoration: underline;
            }
        }
    }
}
</style>
