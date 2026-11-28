<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()
const open = ref(false)
const root = ref(null)

const initials = computed(() =>
  (auth.user?.name ?? '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join(''),
)

async function signOut() {
  open.value = false
  await auth.logout()
  router.replace({ name: 'login' })
}

function onClickOutside(event) {
  if (open.value && root.value && !root.value.contains(event.target)) open.value = false
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="flex items-center gap-2 rounded-lg p-1 pr-2 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-brand-600/40 focus-visible:outline-none"
      :aria-expanded="open"
      aria-haspopup="menu"
      @click="open = !open"
    >
      <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">{{ initials }}</span>
      <span class="hidden max-w-[10rem] truncate text-sm font-medium text-slate-700 md:block">{{ auth.user?.name }}</span>
      <AppIcon name="ChevronDown" :size="14" class="hidden text-slate-400 md:block" />
    </button>
    <div v-if="open" role="menu" class="absolute right-0 z-40 mt-2 w-60 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-xl">
      <div class="border-b border-line px-4 py-3">
        <p class="truncate text-sm font-semibold">{{ auth.user?.name }}</p>
        <p class="truncate text-xs text-muted">{{ auth.user?.email }}</p>
        <p v-if="auth.company" class="mt-1 truncate text-xs text-slate-500">{{ auth.company.name }}</p>
      </div>
      <RouterLink role="menuitem" :to="{ name: 'account' }" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="open = false">
        <AppIcon name="User" :size="16" /> {{ t('nav.account') }}
      </RouterLink>
      <button role="menuitem" type="button" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50" @click="signOut">
        <AppIcon name="LogOut" :size="16" /> {{ t('nav.logout') }}
      </button>
    </div>
  </div>
</template>
