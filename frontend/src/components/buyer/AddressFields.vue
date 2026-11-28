<script setup>
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/ui/BaseInput.vue'

/** Address field group; `errorFor(field)` maps server errors (supports nested prefixes). */
const address = defineModel({ type: Object, required: true })
defineProps({
  withLabel: { type: Boolean, default: true },
  errorFor: { type: Function, default: () => null },
})
const { t } = useI18n()
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <BaseInput v-if="withLabel" v-model="address.label" class="sm:col-span-2" :label="t('addresses.label')" :placeholder="t('addresses.label_placeholder')" required maxlength="100" :error="errorFor('label')" />
    <BaseInput v-model="address.contact_name" :label="t('addresses.contact_name')" required maxlength="255" :error="errorFor('contact_name')" />
    <BaseInput v-model="address.contact_phone" :label="t('addresses.contact_phone')" type="tel" inputmode="tel" placeholder="01XXXXXXXXX" required :error="errorFor('contact_phone')" />
    <BaseInput v-model="address.address_line1" class="sm:col-span-2" :label="t('addresses.address_line1')" required maxlength="255" :error="errorFor('address_line1')" />
    <BaseInput v-model="address.area" :label="t('addresses.area')" maxlength="100" :error="errorFor('area')" />
    <BaseInput v-model="address.city" :label="t('addresses.city')" required maxlength="100" :error="errorFor('city')" />
    <BaseInput v-model="address.district" :label="t('addresses.district')" maxlength="100" :error="errorFor('district')" />
    <BaseInput v-model="address.postal_code" :label="t('addresses.postal_code')" inputmode="numeric" maxlength="4" :error="errorFor('postal_code')" />
  </div>
</template>
