import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [vue(), tailwindcss()],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      port: 5173,
      // Dev: proxy API calls to Laravel so the SPA and API share an origin (no CORS in dev).
      proxy: {
        '/api': { target: env.VITE_DEV_API_PROXY || 'http://127.0.0.1:8000', changeOrigin: true },
      },
    },
    build: {
      sourcemap: false,
      chunkSizeWarningLimit: 600,
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes('node_modules')) {
              if (id.includes('@lucide')) return 'icons'
              if (id.includes('vue-i18n') || id.includes('@intlify')) return 'i18n'
              return 'vendor'
            }
          },
        },
      },
    },
    test: {
      environment: 'jsdom',
      globals: false,
      include: ['tests/**/*.test.js'],
    },
  }
})
