<template>
    <div class="signd-register-form">
        <h3>{{ t('integration_signd', 'Register new signd.it account') }}</h3>
        <p class="signd-description">
            {{ t('integration_signd', 'Create a new signd.it account. The API key will be configured automatically.') }}
        </p>

        <!-- Pricing -->
        <div v-if="prices" class="signd-pricing">
            <h4>{{ t('integration_signd', 'Choose a plan') }}</h4>
            <div class="signd-plan-options">
                <NcCheckboxRadioSwitch
                    v-for="plan in planOptions"
                    :key="plan.value"
                    v-model:checked="form.productPlan"
                    :value="plan.value"
                    name="productPlan"
                    type="radio">
                    <strong>{{ plan.label }}</strong> —
                    {{ t('integration_signd', '{price} €/month per user, {included} processes included', {
                        price: plan.price.perMonthAndUser,
                        included: plan.price.includedProcessesPerMonth,
                    }) }}
                </NcCheckboxRadioSwitch>
            </div>
        </div>
        <div v-else-if="pricesLoading" class="signd-prices-loading">
            <NcLoadingIcon :size="20" />
            {{ t('integration_signd', 'Loading pricing information...') }}
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
            <NcTextField v-model="form.country" :label="t('integration_signd', 'Country code (e.g. DE)')" :disabled="isLoading" />
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
            <NcCheckboxRadioSwitch v-model:checked="form.agbAccepted" :disabled="isLoading">
                {{ t('integration_signd', 'I accept the') }}
                <a :href="termsUrl" target="_blank" rel="noopener">{{ t('integration_signd', 'Terms and Conditions') }}</a>
            </NcCheckboxRadioSwitch>
            <NcCheckboxRadioSwitch v-model:checked="form.dsbAccepted" :disabled="isLoading">
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
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

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
                country: 'DE',
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

        planOptions(): Array<{ value: string; label: string; price: PriceInfo }> {
            if (!this.prices) return []
            return [
                { value: 'premium', label: 'Premium', price: this.prices.premium },
                { value: 'enterprise', label: 'Enterprise', price: this.prices.enterprise },
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
                const error = e as { response?: { data?: { error?: string } } }
                this.error = error.response?.data?.error || t('integration_signd', 'Registration failed')
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
