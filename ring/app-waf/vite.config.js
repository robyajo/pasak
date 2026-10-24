import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { createProxyHandler } from './server/proxy.js';
import { createStrixHandler } from './server/strix.js';

/**
 * Mendaftarkan proxy pengujian WAF ke dev-server (dan preview-server) Vite.
 *
 * Proxy ini harus berjalan di sisi server karena browser melarang JavaScript
 * menyetel header seperti `CF-Connecting-IP` atau `X-Forwarded-For`
 * (forbidden headers). Dengan begini satu perintah `npm run dev` sudah cukup.
 */
function wafTestingProxyPlugin() {
  return {
    name: 'waf-testing-proxy',
    configureServer(server) {
      server.middlewares.use(createProxyHandler());
      server.middlewares.use(createStrixHandler());
    },
    configurePreviewServer(server) {
      server.middlewares.use(createProxyHandler());
      server.middlewares.use(createStrixHandler());
    },
  };
}

export default defineConfig({
  plugins: [react(), tailwindcss(), wafTestingProxyPlugin()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5175,
    strictPort: false,
    open: false,
  },
  preview: {
    port: 5175,
    strictPort: false,
  },
});
