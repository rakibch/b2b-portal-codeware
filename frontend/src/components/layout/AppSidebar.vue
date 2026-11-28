<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { adminNavigation, buyerNavigation, visibleNavigation } from '@/router/navigation'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useFormat } from '@/composables/useFormat'

const props = defineProps({ badges: { type: Object, default: () => ({}) } })
const emit = defineEmits(['navigate'])

const auth = useAuthStore()
const cart = useCartStore()
const router = useRouter()
const { t } = useI18n()
const { formatNumber } = useFormat()

const sections = computed(() =>
  visibleNavigation(auth.isStaff ? adminNavigation : buyerNavigation, {
    hasRoute: (name) => router.hasRoute(name),
    can: auth.can,
    canTransact: auth.canTransact,
  }),
)

function badgeFor(item) {
  if (item.badge === 'cart') return cart.count
  return props.badges[item.badge] ?? 0
}
</script>

<template>
  <nav class="flex h-full flex-col" :aria-label="auth.isStaff ? t('app.admin_portal') : t('app.buyer_portal')">
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-white/10 px-5">
      <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10"><AppIcon name="ShoppingCart" :size="18" class="text-white" /></span>
      <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-white">{{ t('app.name') }}</p>
        <p class="text-[11px] text-brand-200">{{ auth.isStaff ? t('app.admin_portal') : t('app.buyer_portal') }}</p>
      </div>
    </div>

    <div class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
      <div v-for="(section, index) in sections" :key="index">
        <p v-if="section.titleKey" class="mb-2 px-3 text-[11px] font-semibold tracking-wider text-brand-200/80 uppercase">{{ t(section.titleKey) }}</p>
        <ul class="space-y-0.5">
          <li v-for="item in section.items" :key="item.name">
            <RouterLink
              :to="{ name: item.name }"
              class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-brand-50/90 transition-colors hover:bg-white/10 hover:text-white"
              active-class="!bg-white/15 !text-white"
              @click="emit('navigate')"
            >
              <AppIcon :name="item.icon" :size="18" class="shrink-0 opacity-80 group-hover:opacity-100" />
              <span class="flex-1 truncate">{{ t(item.labelKey) }}</span>
              <span v-if="badgeFor(item) > 0" class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-semibold text-white">{{ formatNumber(badgeFor(item)) }}</span>
            </RouterLink>
          </li>
        </ul>
      </div>
    </div>

    <div v-if="auth.company" class="border-t border-white/10 px-5 py-4">
      <p class="truncate text-xs font-semibold text-white">{{ auth.company.name }}</p>
      <p class="truncate text-[11px] text-brand-200">{{ auth.company.kyc?.status?.label }}</p>
    </div>
  </nav>
</template>
