<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import LocaleSwitcher from './LocaleSwitcher.vue'
import NotificationBell from './NotificationBell.vue'
import UserMenu from './UserMenu.vue'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useFormat } from '@/composables/useFormat'
import { useRouter } from 'vue-router'

defineEmits(['open-menu'])

const auth = useAuthStore()
const cart = useCartStore()
const router = useRouter()
const { t } = useI18n()
const { formatNumber } = useFormat()
</script>

<template>
  <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur sm:px-6">
    <button type="button" class="-ml-1 rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" :aria-label="t('common.open_menu')" @click="$emit('open-menu')">
      <AppIcon name="Menu" :size="22" />
    </button>

    <div class="min-w-0 flex-1">
      <p v-if="auth.company" class="truncate text-sm font-medium text-slate-600 lg:hidden">{{ auth.company.name }}</p>
    </div>

    <LocaleSwitcher />

    <RouterLink
      v-if="auth.isBuyer && auth.canTransact && router.hasRoute('buyer.cart')"
      :to="{ name: 'buyer.cart' }"
      class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
      :aria-label="t('nav.cart')"
    >
      <AppIcon name="ShoppingCart" :size="20" />
      <span v-if="cart.count > 0" class="absolute top-1 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-700 px-1 text-[10px] font-bold text-white">
        {{ formatNumber(cart.count) }}
      </span>
    </RouterLink>

    <NotificationBell />
    <UserMenu />
  </header>
</template>
