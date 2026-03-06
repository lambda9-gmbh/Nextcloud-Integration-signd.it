<template>
    <div v-if="signers.length > 0" class="signd-signers">
        <strong>{{ label }}</strong>
        <div
            v-for="signer in signers"
            :key="signer.id"
            :class="['signd-signer', variantClass]">
            {{ signer.clearName || signer.email || t('integration_signd', 'Unknown') }}
            <span v-if="signer.signed" class="signd-signer-date">
                ({{ formatDate(signer.signed) }})
            </span>
            <span v-if="signer.rejected" class="signd-signer-date">
                ({{ formatDate(signer.rejected) }})
            </span>
            <img
                v-if="signer.signatureKey"
                :src="signatureUrl(signer.signatureKey)"
                :alt="t('integration_signd', 'Signature of {name}', { name: signer.clearName || signer.email || '' })"
                class="signd-signature-image">
            <span v-if="signer.rejectedReason" class="signd-signer-reason">
                {{ signer.rejectedReason }}
            </span>
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

interface Signer {
    id: string
    clearName?: string
    email?: string
    signed?: string
    rejected?: string
    signatureKey?: string
    rejectedReason?: string
}

export default defineComponent({
    name: 'SignerList',

    props: {
        label: {
            type: String,
            required: true,
        },
        signers: {
            type: Array as PropType<Signer[]>,
            required: true,
        },
        variant: {
            type: String as PropType<'default' | 'rejected' | 'pending'>,
            default: 'default',
        },
    },

    computed: {
        variantClass(): string {
            if (this.variant === 'rejected') return 'signd-signer--rejected'
            if (this.variant === 'pending') return 'signd-signer--pending'
            return ''
        },
    },

    methods: {
        t,

        signatureUrl(signatureKey: string): string {
            return generateUrl('/apps/integration_signd/api/signature/{signatureKey}', { signatureKey })
        },

        formatDate(dateStr: string): string {
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
.signd-signers {
    margin-bottom: 6px;
    font-size: 13px;

    strong {
        display: block;
        margin-bottom: 2px;
    }
}

.signd-signer {
    padding-left: 12px;

    &--rejected {
        color: var(--color-error-text);
    }

    &--pending {
        color: var(--color-text-maxcontrast);
    }
}

.signd-signer-date {
    color: var(--color-text-maxcontrast);
    font-size: 12px;
}

.signd-signature-image {
    display: block;
    max-height: 60px;
    max-width: 100%;
    margin-top: 4px;
}

.signd-signer-reason {
    display: block;
    font-size: 12px;
    font-style: italic;
    margin-top: 2px;
}
</style>
