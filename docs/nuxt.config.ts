import { copyFileSync, mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

// The changelog lives at the repository root (CHANGELOG.md). Nuxt Content cannot watch the whole
// repository, so the file is copied next to the site, for the "changelog" collection.
const changelogDir = fileURLToPath(new URL('.changelog', import.meta.url))
mkdirSync(changelogDir, { recursive: true })
copyFileSync(fileURLToPath(new URL('../CHANGELOG.md', import.meta.url)), `${changelogDir}/CHANGELOG.md`)

export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  modules: ['@nuxt/ui', '@nuxt/content', '@nuxt/eslint'],
  devtools: { enabled: false },
  css: ['~/assets/css/main.css'],
  ui: { fonts: false },
  app: {
    head: { htmlAttrs: { lang: 'fr' } },
  },
  content: {
    // node:sqlite (Node >= 22.5): no native module to compile.
    experimental: { sqliteConnector: 'native' },
    build: {
      markdown: {
        highlight: {
          langs: ['bash', 'json', 'html', 'js', 'ts', 'vue', 'tsx', 'php', 'python', 'yaml', 'http'],
        },
      },
    },
  },
  icon: {
    // Served from the installed @iconify-json packages, never from the Iconify API.
    serverBundle: 'local',
    clientBundle: {
      scan: true,
      // Icons referenced from Markdown/YAML (not visible to the source scan).
      icons: [
        'lucide:arrow-right', 'lucide:book-open', 'lucide:code', 'lucide:code-xml', 'lucide:download', 'lucide:folder',
        'lucide:folder-tree', 'lucide:hard-drive', 'lucide:history', 'lucide:house', 'lucide:laptop',
        'lucide:layout-dashboard', 'lucide:lock', 'lucide:play', 'lucide:refresh-cw', 'lucide:rocket', 'lucide:send',
        'lucide:settings', 'lucide:share-2', 'lucide:shield-check', 'lucide:terminal', 'lucide:users', 'simple-icons:github',
        // Code block titles ([Terminal], [.env]).
        'vscode-icons:file-type-dotenv',
      ],
    },
  },
  nitro: {
    prerender: { routes: ['/'], crawlLinks: true },
  },
})
