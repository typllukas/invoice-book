import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [react()],
  resolve: { tsconfigPaths: true },
  server: {
    host: '0.0.0.0',
    port: 5173,
    // one origin for the browser, so no CORS; /bundles are the Swagger UI assets
    proxy: {
      '/api': 'http://nginx:80',
      '/bundles': 'http://nginx:80',
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./vitest.setup.ts'],
    restoreMocks: true,
    // the dates must print the same on any machine, and Prague is where the invoices are read
    env: { TZ: 'Europe/Prague' },
  },
})
