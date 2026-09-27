<script setup lang="ts">
import type { ExplorerItem, ExplorerLocation } from '#file-explorer'
import type { StorageUsage } from '~/types/api'

// "Mes fichiers": the reusable explorer (@rocket/file-explorer) on the user's private space, plus the quota and sharing.
const app = useAppConfig().rocket
useHead({ title: `Mes fichiers · ${app.name}` })
const api = useApi()
const route = useRoute()
const router = useRouter()

const { data: usage, refresh: refreshUsage } = await useAsyncData('usage', () => api<StorageUsage>('/api/files/usage'))
const adapter = useCloudExplorer(usage)
const explorer = ref<{ refresh: () => Promise<void>, pickFiles: () => void } | null>(null)

// The current folder is in the URL (?folder=<id>), so links and the back button work
const location = computed<ExplorerLocation>({
  get: () => ({ space: CLOUD_SPACE, folder: typeof route.query.folder === 'string' ? route.query.folder : null }),
  set: v => router.push({ query: v.folder ? { folder: String(v.folder) } : {} }),
})

// Dashboard shortcut "Déposer des fichiers" (/files?upload=1)
onMounted(() => {
  if (route.query.upload) nextTick(() => explorer.value?.pickFiles())
})

const sharing = ref<{ type: 'file' | 'folder', id: string, name: string } | null>(null)
function onAction(id: string, items: ExplorerItem[]) {
  const item = items[0]
  if (id === 'share' && item && item.kind !== 'space') sharing.value = { type: item.kind, id: String(item.id), name: item.name }
}
</script>

<template>
  <UDashboardPanel id="files">
    <template #header>
      <UDashboardNavbar title="Mes fichiers">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <div v-if="usage" class="hidden w-56 flex-col gap-1 text-xs text-muted sm:flex" data-testid="usage">
            <UProgress :model-value="usage.quota ? (100 * usage.used) / usage.quota : 0" size="xs" :color="usage.used / usage.quota > 0.9 ? 'warning' : 'primary'" />
            {{ formatSize(usage.used) }} utilisés sur {{ formatSize(usage.quota) }}
          </div>
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <RocketFileExplorer
        ref="explorer" v-model:location="location" :adapter="adapter" :space="CLOUD_SPACE" height="calc(100vh - 7rem)"
        @action="onAction" @changed="refreshUsage"
      />
      <FilesShareModal :target="sharing" @close="sharing = null" />
    </template>
  </UDashboardPanel>
</template>
