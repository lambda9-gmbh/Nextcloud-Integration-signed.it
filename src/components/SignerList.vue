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
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import { translate as t } from '@nextcloud/l10n'

interface Signer {
    id: string
    clearName?: string
    email?: string
    signed?: string
    rejected?: string
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
        color: var(--color-error);
    }

    &--pending {
        color: var(--color-text-maxcontrast);
    }
}

.signd-signer-date {
    color: var(--color-text-maxcontrast);
    font-size: 12px;
}
</style>
