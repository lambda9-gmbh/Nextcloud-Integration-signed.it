<template>
    <div class="signd-start-process">
        <NcButton
            variant="primary"
            wide
            :disabled="isStarting"
            @click="startProcess">
            <template #icon>
                <NcLoadingIcon v-if="isStarting" :size="20" />
            </template>
            {{ t('integration_signd', 'Start signing process') }}
        </NcButton>

        <NcNoteCard v-if="error" type="error">
            {{ error }}
        </NcNoteCard>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import { processApi, extractErrorMessage } from '../services/api'

export default defineComponent({
    name: 'StartProcessButton',

    components: {
        NcButton,
        NcNoteCard,
        NcLoadingIcon,
    },

    props: {
        fileId: {
            type: Number,
            required: true,
        },
        fileName: {
            type: String,
            default: '',
        },
    },

    emits: ['started'],

    data() {
        return {
            isStarting: false,
            error: '',
        }
    },

    methods: {
        t,

        async startProcess() {
            this.isStarting = true
            this.error = ''

            try {
                const result = await processApi.startWizard(this.fileId)

                // Open wizard URL in new tab
                if (result.wizardUrl) {
                    window.open(result.wizardUrl, '_blank')
                }

                this.$emit('started', result)
            } catch (e) {
                this.error = extractErrorMessage(e, t('integration_signd', 'Failed to start signing process. Please try again.'))
            } finally {
                this.isStarting = false
            }
        },
    },
})
</script>

<style lang="scss" scoped>
.signd-start-process {
    margin-top: 16px;
}
</style>
