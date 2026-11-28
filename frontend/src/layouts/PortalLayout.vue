<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import AnnouncementBar from '@/components/layout/AnnouncementBar.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const { t } = useI18n()
const drawerOpen = ref(false)

function onKeydown(event) {
  if (event.key === 'Escape') drawerOpen.value = false
}

watch(drawerOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
  if (open) document.addEventListener('keydown', onKeydown)
  else document.removeEventListener('keydown', onKeydown)
})

// Close the mobile drawer whenever navigation happens.
watch(() => route.fullPath, () => {
  drawerOpen.value = false
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="min-h-screen bg-surface">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[70] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">
      {{ t('app.skip_to_content') }}
    </a>

    <!-- Desktop sidebar -->
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 bg-gradient-to-b from-brand-900 to-brand-800 lg:block">
      <AppSidebar />
    </aside>

    <!-- Mobile drawer -->
    <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-active-class="transition-opacity duration-150">
      <div v-if="drawerOpen" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" aria-hidden="true" @click="drawerOpen = false" />
    </Transition>
    <Transition enter-from-class="-translate-x-full" leave-to-class="-translate-x-full" enter-active-class="transition-transform duration-200" leave-active-class="transition-transform duration-150">
      <aside v-if="drawerOpen" class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-gradient-to-b from-brand-900 to-brand-800 shadow-xl lg:hidden" role="dialog" aria-modal="true">
        <button type="button" class="absolute top-4 right-3 z-10 rounded-lg p-1.5 text-white/70 hover:bg-white/10 hover:text-white" :aria-label="t('common.close_menu')" @click="drawerOpen = false">
          <AppIcon name="X" :size="20" />
        </button>
        <AppSidebar @navigate="drawerOpen = false" />
      </aside>
    </Transition>

    <div class="flex min-h-screen flex-col lg:pl-64">
      <AppNavbar @open-menu="drawerOpen = true" />
      <AnnouncementBar />
      <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:py-8" tabindex="-1">
        <RouterView v-slot="{ Component }">
          <component :is="Component" />
        </RouterView>
      </main>
    </div>
  </div>
</template>
