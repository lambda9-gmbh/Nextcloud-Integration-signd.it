<template>
    <div class="signd-admin-settings">
        <NcSettingsSection :name="t('integration_signd', 'signd.it integration')">
            <p class="signd-description">
                {{ t('integration_signd', 'Connect your Nextcloud with') }}
                <a href="https://signd.it" target="_blank" rel="noopener">signd.it</a>
                {{ t('integration_signd', 'to digitally sign PDF documents. Once an API key is configured, all users of this Nextcloud instance can start signing processes.') }}
            </p>
            <p class="signd-description">
                {{ t('integration_signd', 'The API key is linked to a single signd.it account. All signing processes run through this account, are billed to it, and are visible to all users regardless of who initiated them.') }}
            </p>

            <div v-if="isLoading" class="signd-loading">
                <NcLoadingIcon :size="32" />
            </div>

            <template v-else>
                <!-- Status display -->
                <div v-if="apiKeySet && !apiKeyValid" class="signd-status">
                    <NcNoteCard type="error">
                        <div class="signd-notecard-content">
                            <div>
                                {{ t('integration_signd', 'The stored API key is no longer valid. Please enter a new key, log in, or register a new account.') }}
                            </div>
                            <NcButton variant="tertiary"
                                :disabled="isDisconnecting"
                                @click="onDisconnect">
                                {{ t('integration_signd', 'Disconnect') }}
                            </NcButton>
                        </div>
                    </NcNoteCard>
                </div>
                <div v-else-if="apiKeySet" class="signd-status">
                    <NcNoteCard type="success">
                        <div class="signd-notecard-content">
                            <div>
                                <p>{{ t('integration_signd', 'API key is configured.') }}</p>
                                <p v-if="userInfo">
                                    {{ t('integration_signd', 'Connected as: {name} ({email})', {
                                        name: userInfo.clearName || '—',
                                        email: userInfo.email,
                                    }) }}
                                </p>
                            </div>
                            <NcButton variant="tertiary"
                                :disabled="isDisconnecting"
                                @click="onDisconnect">
                                {{ t('integration_signd', 'Disconnect') }}
                            </NcButton>
                        </div>
                    </NcNoteCard>
                </div>
                <div v-else class="signd-status">
                    <NcNoteCard type="warning">
                        {{ t('integration_signd', 'No API key configured. Please enter your API key, log in, or register a new account.') }}
                    </NcNoteCard>
                </div>

                <!-- Tab navigation (only when not connected) -->
                <template v-if="!apiKeySet || !apiKeyValid">
                    <div class="signd-tabs">
                        <NcButton
                            v-for="tab in tabs"
                            :key="tab.id"
                            :variant="activeTab === tab.id ? 'primary' : 'secondary'"
                            @click="activeTab = tab.id">
                            {{ tab.label }}
                        </NcButton>
                    </div>

                    <!-- Tab content -->
                    <ApiKeyForm
                        v-if="activeTab === 'apikey'"
                        @saved="onApiKeySaved" />
                    <LoginForm
                        v-if="activeTab === 'login'"
                        @logged-in="onLoggedIn" />
                    <RegisterForm
                        v-if="activeTab === 'register'"
                        @registered="onRegistered" />
                </template>
            </template>
        </NcSettingsSection>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import ApiKeyForm from './ApiKeyForm.vue'
import LoginForm from './LoginForm.vue'
import RegisterForm from './RegisterForm.vue'

import { settingsApi } from '../services/api'
import type { UserInfo } from '../services/api'

export default defineComponent({
    name: 'AdminSettings',

    components: {
        NcSettingsSection,
        NcButton,
        NcNoteCard,
        NcLoadingIcon,
        ApiKeyForm,
        LoginForm,
        RegisterForm,
    },

    data() {
        return {
            isLoading: false,
            isDisconnecting: false,
            apiKeySet: loadState('integration_signd', 'api_key_set', false) as boolean,
            apiKeyValid: loadState('integration_signd', 'api_key_valid', true) as boolean,
            userInfo: loadState('integration_signd', 'user_info', null) as UserInfo | null,
            activeTab: 'apikey' as string,
        }
    },

    computed: {
        tabs() {
            return [
                { id: 'apikey', label: t('integration_signd', 'API Key') },
                { id: 'login', label: t('integration_signd', 'Login') },
                { id: 'register', label: t('integration_signd', 'Register') },
            ]
        },
    },

    methods: {
        t,

        onApiKeySaved(result: { userInfo?: UserInfo }) {
            this.apiKeySet = true
            this.apiKeyValid = true
            if (result.userInfo) {
                this.userInfo = result.userInfo
            }
        },

        onLoggedIn(result: { userInfo?: UserInfo }) {
            this.apiKeySet = true
            this.apiKeyValid = true
            if (result.userInfo) {
                this.userInfo = result.userInfo
            }
        },

        async onRegistered() {
            this.apiKeySet = true
            this.apiKeyValid = true
            try {
                const config = await settingsApi.getConfig()
                this.userInfo = config.userInfo
            } catch {
                this.userInfo = null
            }
        },

        async onDisconnect() {
            this.isDisconnecting = true
            try {
                await settingsApi.deleteApiKey()
                this.apiKeySet = false
                this.apiKeyValid = true
                this.userInfo = null
            } catch {
                // ignore — unlikely to fail
            } finally {
                this.isDisconnecting = false
            }
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-admin-settings {
    .signd-description {
        margin-bottom: 8px;
        color: var(--color-text-maxcontrast);

        a {
            color: var(--color-primary-element);
        }
    }

    .signd-loading {
        display: flex;
        justify-content: center;
        padding: 20px;
    }

    .signd-status {
        margin-bottom: 16px;

        :deep(.notecard > div:not(.notecard__icon)) {
            flex-grow: 1;
        }
    }

    .signd-notecard-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
    }

    .signd-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
    }
}
</style>
