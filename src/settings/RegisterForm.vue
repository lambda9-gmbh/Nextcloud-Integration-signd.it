<template>
    <div class="signd-register-form">
        <h3>{{ t('integration_signd', 'Register new signd.it account') }}</h3>
        <p class="signd-description">
            {{ t('integration_signd', 'Create a new signd.it account. The API key will be configured automatically.') }}
        </p>
        <p class="signd-description">
            {{ t('integration_signd', 'signd.it includes default message texts for email notifications (e.g. "Hello" as salutation and "Kind regards" as closing). You should adapt these texts to match your corporate language. You can view and edit them in signd.it under the "notification texts" menu item.') }}
        </p>

        <!-- Pricing -->
        <div class="signd-pricing">
            <h4>{{ t('integration_signd', 'Choose a plan') }}</h4>
            <p class="signd-pricing-link">
                <a :href="pricingUrl" target="_blank" rel="noopener">{{ t('integration_signd', 'Compare plans and pricing on signd.it') }}</a>
            </p>
            <div class="signd-plan-options">
                <NcCheckboxRadioSwitch
                    v-for="plan in planOptions"
                    :key="plan.value"
                    :model-value="form.productPlan"
                    :value="plan.value"
                    name="productPlan"
                    type="radio"
                    @update:model-value="form.productPlan = $event">
                    <strong>{{ plan.label }}</strong>
                    <template v-if="plan.price">
                        — {{ t('integration_signd', '{price} €/month per user, {included} processes included', {
                            price: plan.price.perMonthAndUser,
                            included: plan.price.includedProcessesPerMonth,
                        }) }}
                    </template>
                </NcCheckboxRadioSwitch>
            </div>
        </div>

        <!-- Organisation -->
        <h4>{{ t('integration_signd', 'Organisation') }}</h4>
        <div class="signd-form-field">
            <NcTextField v-model="form.organisation" :label="t('integration_signd', 'Organisation name')" :disabled="isLoading" />
        </div>

        <!-- Address -->
        <div class="signd-form-row">
            <div class="signd-form-field signd-field-wide">
                <NcTextField v-model="form.street" :label="t('integration_signd', 'Street')" :disabled="isLoading" />
            </div>
            <div class="signd-form-field signd-field-narrow">
                <NcTextField v-model="form.houseNumber" :label="t('integration_signd', 'House number')" :disabled="isLoading" />
            </div>
        </div>
        <div class="signd-form-row">
            <div class="signd-form-field signd-field-narrow">
                <NcTextField v-model="form.zipCode" :label="t('integration_signd', 'ZIP code')" :disabled="isLoading" />
            </div>
            <div class="signd-form-field signd-field-wide">
                <NcTextField v-model="form.city" :label="t('integration_signd', 'City')" :disabled="isLoading" />
            </div>
        </div>
        <div class="signd-form-field">
            <NcSelect
                v-model="selectedCountry"
                :options="countryOptions"
                :placeholder="t('integration_signd', 'Country')"
                :disabled="isLoading"
                label="label"
                input-id="signd-country-select" />
        </div>

        <!-- Personal -->
        <h4>{{ t('integration_signd', 'Account details') }}</h4>
        <div class="signd-form-field">
            <NcTextField v-model="form.clearName" :label="t('integration_signd', 'Full name')" :disabled="isLoading" />
        </div>
        <div class="signd-form-field">
            <NcTextField v-model="form.email" :label="t('integration_signd', 'Email')" type="email" :disabled="isLoading" />
        </div>
        <div class="signd-form-field">
            <NcPasswordField v-model="form.password" :label="t('integration_signd', 'Password')" :disabled="isLoading" />
        </div>

        <!-- Optional -->
        <div class="signd-form-field">
            <NcTextField v-model="form.vatId" :label="t('integration_signd', 'VAT ID (optional)')" :disabled="isLoading" />
        </div>
        <div class="signd-form-field">
            <NcTextField v-model="form.couponCode" :label="t('integration_signd', 'Coupon code (optional)')" :disabled="isLoading" />
        </div>

        <!-- Legal -->
        <div class="signd-legal">
            <NcCheckboxRadioSwitch v-model="form.agbAccepted" :disabled="isLoading">
                {{ t('integration_signd', 'I accept the') }}
                <a :href="termsUrl" target="_blank" rel="noopener">{{ t('integration_signd', 'Terms and Conditions') }}</a>
            </NcCheckboxRadioSwitch>
            <NcCheckboxRadioSwitch v-model="form.dsbAccepted" :disabled="isLoading">
                {{ t('integration_signd', 'I accept the') }}
                <a :href="privacyUrl" target="_blank" rel="noopener">{{ t('integration_signd', 'Privacy Policy') }}</a>
            </NcCheckboxRadioSwitch>
        </div>

        <NcNoteCard v-if="error" type="error">
            {{ error }}
        </NcNoteCard>

        <NcButton
            variant="primary"
            :disabled="!isFormValid || isLoading"
            @click="register">
            <template #icon>
                <NcLoadingIcon v-if="isLoading" :size="20" />
            </template>
            {{ t('integration_signd', 'Register') }}
        </NcButton>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t, getLanguage } from '@nextcloud/l10n'
import countries from 'i18n-iso-countries'
import countriesDE from 'i18n-iso-countries/langs/de.json'
import countriesEN from 'i18n-iso-countries/langs/en.json'
import countriesES from 'i18n-iso-countries/langs/es.json'
import countriesFR from 'i18n-iso-countries/langs/fr.json'
import countriesIT from 'i18n-iso-countries/langs/it.json'
import countriesPT from 'i18n-iso-countries/langs/pt.json'
import countriesDA from 'i18n-iso-countries/langs/da.json'
import countriesPL from 'i18n-iso-countries/langs/pl.json'

countries.registerLocale(countriesDE)
countries.registerLocale(countriesEN)
countries.registerLocale(countriesES)
countries.registerLocale(countriesFR)
countries.registerLocale(countriesIT)
countries.registerLocale(countriesPT)
countries.registerLocale(countriesDA)
countries.registerLocale(countriesPL)

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import { settingsApi } from '../services/api'
import type { PriceInfo, PricesResponse } from '../services/api'

export default defineComponent({
    name: 'RegisterForm',

    components: {
        NcButton,
        NcTextField,
        NcPasswordField,
        NcCheckboxRadioSwitch,
        NcNoteCard,
        NcLoadingIcon,
        NcSelect,
    },

    emits: ['registered'],

    data() {
        return {
            form: {
                productPlan: 'premium',
                organisation: '',
                street: '',
                houseNumber: '',
                zipCode: '',
                city: '',
                country: '',
                clearName: '',
                email: '',
                password: '',
                vatId: '',
                couponCode: '',
                agbAccepted: false,
                dsbAccepted: false,
            },
            prices: null as PricesResponse | null,
            pricesLoading: false,
            isLoading: false,
            error: '',
        }
    },

    computed: {
        // signd server URL for legal links (default https://signd.it)
        serverUrl(): string {
            return 'https://signd.it'
        },

        termsUrl(): string {
            return `${this.serverUrl}/terms-and-conditions`
        },

        privacyUrl(): string {
            return `${this.serverUrl}/privacy-policy`
        },

        countryLang(): string {
            const supported = ['de', 'en', 'es', 'fr', 'it', 'pt', 'da', 'pl']
            const lang = getLanguage().split('-')[0].toLowerCase()
            return supported.includes(lang) ? lang : 'en'
        },

        pricingUrl(): string {
            return `${this.serverUrl}/pages/${this.countryLang}/preise/`
        },

        countryOptions(): Array<{ code: string; label: string }> {
            const names = countries.getNames(this.countryLang)
            return Object.entries(names)
                .map(([code, label]) => ({ code, label: label as string }))
                .sort((a, b) => a.label.localeCompare(b.label, this.countryLang))
        },

        selectedCountry: {
            get(): { code: string; label: string } | null {
                return this.countryOptions.find(c => c.code === this.form.country) ?? null
            },
            set(option: { code: string; label: string } | null) {
                this.form.country = option?.code ?? ''
            },
        },

        planOptions(): Array<{ value: string; label: string; price: PriceInfo | null }> {
            return [
                { value: 'premium', label: 'Premium', price: this.prices?.premium ?? null },
                { value: 'enterprise', label: 'Enterprise', price: this.prices?.enterprise ?? null },
            ]
        },

        isFormValid(): boolean {
            return !!(
                this.form.productPlan
                && this.form.organisation
                && this.form.street
                && this.form.houseNumber
                && this.form.zipCode
                && this.form.city
                && this.form.country
                && this.form.clearName
                && this.form.email
                && this.form.password
                && this.form.agbAccepted
                && this.form.dsbAccepted
            )
        },
    },

    async mounted() {
        await this.loadPrices()
    },

    methods: {
        t,

        async loadPrices() {
            this.pricesLoading = true
            try {
                this.prices = await settingsApi.getPrices()
            } catch {
                // Prices are optional for the form
            } finally {
                this.pricesLoading = false
            }
        },

        async register() {
            this.isLoading = true
            this.error = ''

            try {
                const result = await settingsApi.register(this.form)
                this.$emit('registered', result)
            } catch (e: unknown) {
                const error = e as { response?: { data?: { error?: string, errorCode?: string } } }
                if (error.response?.data?.errorCode === 'SIGND_UNREACHABLE') {
                    this.error = t('integration_signd', 'Cannot reach the signd.it server. Please try again later.')
                } else {
                    this.error = error.response?.data?.error || t('integration_signd', 'Registration failed')
                }
            } finally {
                this.isLoading = false
            }
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-register-form {
    .signd-description {
        color: var(--color-text-maxcontrast);
        margin-bottom: 12px;
    }

    h4 {
        margin-top: 20px;
        margin-bottom: 8px;
    }

    .signd-pricing-link {
        margin-bottom: 8px;

        a {
            color: var(--color-primary-element);
        }
    }

    .signd-form-field {
        margin-bottom: 12px;
        max-width: 400px;
    }

    .signd-form-row {
        display: flex;
        gap: 12px;
        max-width: 400px;

        .signd-field-wide {
            flex: 2;
        }

        .signd-field-narrow {
            flex: 1;
        }
    }

    .signd-pricing {
        margin-bottom: 16px;
    }

    .signd-plan-options {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .signd-prices-loading {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--color-text-maxcontrast);
        margin-bottom: 12px;
    }

    .signd-legal {
        margin: 16px 0;

        a {
            color: var(--color-primary-element);
        }
    }
}
</style>
