import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        configure(proxy) {
          proxy.on('error', (_error, _request, response) => {
            if (typeof response.writeHead !== 'function' || response.headersSent || response.writableEnded) return
            response.writeHead(503, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' })
            response.end(JSON.stringify({ data: null, error: { code: 'API_UNAVAILABLE', message: 'Serviço temporariamente indisponível. Tente novamente em instantes.' } }))
          })
        },
      },
    },
  },
})
