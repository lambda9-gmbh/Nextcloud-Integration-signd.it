<template>
    <div class="toolbar">
        <NcTextField
            class="toolbar__search"
            :model-value="searchQuery"
            :label="t('integration_signd', 'Search...')"
            :show-trailing-button="searchQuery !== ''"
            trailing-button-icon="close"
            @update:model-value="onSearchInput"
            @trailing-button-click="clearSearch" />

        <div class="toolbar__filters">
            <NcSelect
                v-model="selectedStatus"
                class="toolbar__status"
                :options="statusOptions"
                :clearable="false"
                :searchable="false"
                label="label" />

            <NcDateTimePicker
                v-model="dateFromModel"
                class="toolbar__date"
                type="date"
                :clearable="true"
                :placeholder="t('integration_signd', 'From')" />

            <NcDateTimePicker
                v-model="dateToModel"
                class="toolbar__date"
                type="date"
                :clearable="true"
                :placeholder="t('integration_signd', 'To')" />

            <NcCheckboxRadioSwitch
                :model-value="onlyMine"
                type="switch"
                @update:model-value="$emit('update:onlyMine', $event)">
                {{ t('integration_signd', 'Only mine') }}
            </NcCheckboxRadioSwitch>
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'

import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

interface StatusOption {
    value: string
    label: string
}

export default defineComponent({
    name: 'OverviewToolbar',

    components: {
        NcDateTimePicker,
        NcSelect,
        NcTextField,
        NcCheckboxRadioSwitch,
    },

    props: {
        status: {
            type: String,
            default: 'ALL',
        },
        searchQuery: {
            type: String,
            default: '',
        },
        dateFrom: {
            type: String,
            default: '',
        },
        dateTo: {
            type: String,
            default: '',
        },
        onlyMine: {
            type: Boolean,
            default: false,
        },
    },

    emits: [
        'update:status',
        'update:searchQuery',
        'update:dateFrom',
        'update:dateTo',
        'update:onlyMine',
    ],

    data() {
        return {
            dateFromModel: null as Date | null,
            dateToModel: null as Date | null,
            statusOptions: [
                { value: 'ALL', label: t('integration_signd', 'All') },
                { value: 'RUNNING', label: t('integration_signd', 'Running') },
                { value: 'FINISHED', label: t('integration_signd', 'Finished') },
            ] as StatusOption[],
            searchDebounceTimer: null as ReturnType<typeof setTimeout> | null,
        }
    },

    computed: {
        selectedStatus: {
            get(): StatusOption {
                return this.statusOptions.find((o) => o.value === this.status) || this.statusOptions[0]
            },
            set(option: StatusOption) {
                this.$emit('update:status', option.value)
            },
        },
    },

    watch: {
        dateFromModel(val: Date | null) {
            this.$emit('update:dateFrom', this.formatDate(val))
        },
        dateToModel(val: Date | null) {
            this.$emit('update:dateTo', this.formatDate(val))
        },
    },

    methods: {
        t,

        onSearchInput(value: string) {
            if (this.searchDebounceTimer) {
                clearTimeout(this.searchDebounceTimer)
            }
            this.searchDebounceTimer = setTimeout(() => {
                this.$emit('update:searchQuery', value)
            }, 400)
        },

        clearSearch() {
            if (this.searchDebounceTimer) {
                clearTimeout(this.searchDebounceTimer)
            }
            this.$emit('update:searchQuery', '')
        },

        formatDate(date: Date | null): string {
            if (!date) return ''
            const y = date.getFullYear()
            const m = String(date.getMonth() + 1).padStart(2, '0')
            const d = String(date.getDate()).padStart(2, '0')
            return `${y}-${m}-${d}`
        },
    },
})
</script>

<style lang="scss" scoped>
.toolbar {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;

    &__search {
        max-width: 500px;
    }

    &__filters {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    &__status {
        width: 150px;
        flex-shrink: 0;
    }

    &__date {
        width: 160px;
        flex-shrink: 0;
    }
}
</style>