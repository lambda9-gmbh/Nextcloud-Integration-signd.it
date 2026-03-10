<!--
  SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
    <div v-if="total > 0" class="signd-pagination">
        <NcButton variant="tertiary" :disabled="offset === 0" @click="$emit('prev')">
            {{ t('integration_signd', 'Previous') }}
        </NcButton>

        <span class="signd-pagination__info">
            {{ t('integration_signd', '{from}–{to} of {total}', { from: rangeFrom, to: rangeTo, total }) }}
        </span>

        <NcButton variant="tertiary" :disabled="offset + limit >= total" @click="$emit('next')">
            {{ t('integration_signd', 'Next') }}
        </NcButton>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'

export default defineComponent({
    name: 'OverviewPagination',

    components: {
        NcButton,
    },

    props: {
        offset: {
            type: Number,
            required: true,
        },
        limit: {
            type: Number,
            required: true,
        },
        total: {
            type: Number,
            required: true,
        },
    },

    emits: ['prev', 'next'],

    computed: {
        rangeFrom(): number {
            return this.offset + 1
        },

        rangeTo(): number {
            return Math.min(this.offset + this.limit, this.total)
        },
    },

    methods: {
        t,
    },
})
</script>

<style lang="scss" scoped>
.signd-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 12px 0;

    &__info {
        font-size: 13px;
        color: var(--color-text-maxcontrast);
    }
}
</style>
