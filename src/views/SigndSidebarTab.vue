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

                <!-- Process list -->
                <ProcessList
                    v-if="processes.length > 0"
                    :processes="processes"
                    @download="downloadPdf"
                    @resume-wizard="resumeWizard"
                    @cancel-wizard="cancelWizard" />

                <!-- No processes -->
                <div v-else class="signd-empty">
                    <p>{{ t('integration_signd', 'No signing processes for this document.') }}</p>
                </div>

                <!-- Start new process button -->
                <StartProcessButton
                    :file-id="fileId"
                    :file-name="fileName"
                    @started="onProcessStarted" />

                <!-- Refresh all button -->
                <div v-if="processes.length > 0" class="signd-refresh">
                    <NcButton
                        variant="tertiary"
                        :disabled="isRefreshing"
                        @click="loadProcesses">
                        <template #icon>
                            <NcLoadingIcon v-if="isRefreshing" :size="20" />
                        </template>
                        {{ t('integration_signd', 'Refresh status') }}
                    </NcButton>
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

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import ProcessList from '../components/ProcessList.vue'
import StartProcessButton from '../components/StartProcessButton.vue'

import { processApi, extractErrorMessage } from '../services/api'
import { notifyFileCreated } from '../services/fileListNotify'
import type { SigndProcess } from '../services/api'

export default defineComponent({
    name: 'SigndSidebarTab',

    components: {
        NcButton,
        NcNoteCard,
        NcLoadingIcon,
        ProcessList,
        StartProcessButton,
    },

    props: {
        fileInfo: {
            type: Object,
            default: null,
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
        }
    },

    computed: {
        fileId(): number {
            return this.fileInfo?.id ?? 0
        },

        fileName(): string {
            return this.fileInfo?.name ?? ''
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
                    this.warning = t('integration_signd', 'Original folder no longer exists. Signed PDF was saved to your home folder.')
                }
                await this.loadProcesses()
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to download signed PDF.'))
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

    .signd-refresh {
        display: flex;
        justify-content: center;
        margin-top: 12px;
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
