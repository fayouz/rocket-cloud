<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { Folder, StorageUsage, StoredFile } from '~/types/api'

const app = useAppConfig().rocket
useHead({ title: `Mes fichiers · ${app.name}` })

const api = useApi()
const auth = useAuth()
const config = useRuntimeConfig()
const route = useRoute()
const router = useRouter()
const toast = useToast()

// The current folder is in the URL (?folder=<id>), so links and the back button work.
const folderId = computed(() => (typeof route.query.folder === 'string' ? route.query.folder : null))
const search = ref('')

const { data: current } = await useAsyncData('folder', () => folderId.value ? api<Folder>(`/api/folders/${folderId.value}`) : Promise.resolve(null), { watch: [folderId] })
const { data: folders, refresh: refreshFolders } = await useAsyncData('folders', () => api<Folder[]>('/api/folders', {
  query: folderId.value ? { parent: folderId.value, itemsPerPage: 500 } : { 'exists[parent]': false, 'itemsPerPage': 500 },
}), { watch: [folderId], default: () => [] })
const { data: files, status, refresh: refreshFiles } = await useAsyncData('files', () => api<StoredFile[]>('/api/files', {
  query: search.value.trim()
    ? { 'name': search.value.trim(), 'itemsPerPage': 500, 'order[name]': 'asc' }
    : { ...(folderId.value ? { folder: folderId.value } : { 'exists[folder]': false }), 'itemsPerPage': 500, 'order[name]': 'asc' },
}), { watch: [folderId], default: () => [] })
const { data: usage, refresh: refreshUsage } = await useAsyncData('usage', () => api<StorageUsage>('/api/files/usage'))

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => refreshFiles(), 250)
})

function open(folder: string | null) {
  search.value = ''
  router.push({ query: folder ? { folder } : {} })
}

async function refreshAll() {
  await Promise.all([refreshFolders(), refreshFiles(), refreshUsage()])
}

// --- Upload -------------------------------------------------------------------------------------

const input = ref<HTMLInputElement | null>(null)
const uploading = ref<{ name: string, done: boolean, error?: string }[]>([])
const dragging = ref(false)

async function upload(list: FileList | File[]) {
  const items = [...list]
  if (!items.length) return
  for (const file of items) {
    const entry = reactive({ name: file.name, done: false, error: undefined as string | undefined })
    uploading.value.push(entry)
    if (usage.value && file.size > usage.value.maxFileSize) {
      entry.error = `Trop volumineux (max. ${formatSize(usage.value.maxFileSize)})`
      entry.done = true
      continue
    }
    const body = new FormData()
    body.append('file', file)
    if (folderId.value) body.append('folder', folderId.value)
    try {
      await api('/api/files', { method: 'POST', body })
    }
    catch (error) {
      entry.error = apiErrorMessage(error)
    }
    entry.done = true
  }
  const failed = uploading.value.filter(u => u.error)
  toast.add(failed.length
    ? { title: `${failed.length} fichier(s) non déposé(s)`, description: failed.map(f => `${f.name} : ${f.error}`).join('\n'), color: 'error' }
    : { title: `${items.length} fichier(s) déposé(s)`, color: 'success' })
  uploading.value = []
  await refreshAll()
}

function onDrop(event: DragEvent) {
  dragging.value = false
  if (event.dataTransfer?.files) upload(event.dataTransfer.files)
}

onMounted(() => {
  if (route.query.upload) input.value?.click()
})

// --- Folders ------------------------------------------------------------------------------------

const naming = ref<{ kind: 'new-folder' | 'rename-folder' | 'rename-file', id?: string, name: string } | null>(null)

async function saveName() {
  const target = naming.value
  if (!target || !target.name.trim()) return
  try {
    if (target.kind === 'new-folder') {
      await api('/api/folders', { method: 'POST', body: { name: target.name, parent: folderId.value ? `/api/folders/${folderId.value}` : null } })
    }
    else {
      await api(`/api/${target.kind === 'rename-folder' ? 'folders' : 'files'}/${target.id}`, { method: 'PATCH', body: { name: target.name } })
    }
    naming.value = null
    await refreshAll()
  }
  catch (error) {
    toast.add({ title: 'Enregistrement impossible', description: apiErrorMessage(error), color: 'error' })
  }
}

// --- Move ---------------------------------------------------------------------------------------

const moving = ref<{ kind: 'file' | 'folder', id: string, name: string } | null>(null)
const { data: allFolders, refresh: refreshAllFolders } = await useAsyncData('all-folders', () => api<Folder[]>('/api/folders', { query: { itemsPerPage: 1000 } }), { default: () => [], immediate: false })
const destination = ref<string>('root')
const destinations = computed(() => [
  { label: 'Racine (Mes fichiers)', value: 'root' },
  ...allFolders.value
    .filter(f => !(moving.value?.kind === 'folder' && f.path.some(p => p.id === moving.value!.id)))
    .map(f => ({ label: f.path.map(p => p.name).join(' / '), value: f.id }))
    .sort((a, b) => a.label.localeCompare(b.label)),
])

async function startMove(target: { kind: 'file' | 'folder', id: string, name: string }) {
  moving.value = target
  destination.value = folderId.value ?? 'root'
  await refreshAllFolders()
}

async function move() {
  const target = moving.value!
  const iri = destination.value === 'root' ? null : `/api/folders/${destination.value}`
  try {
    await api(`/api/${target.kind === 'file' ? 'files' : 'folders'}/${target.id}`, { method: 'PATCH', body: target.kind === 'file' ? { folder: iri } : { parent: iri } })
    moving.value = null
    await refreshAll()
  }
  catch (error) {
    toast.add({ title: 'Déplacement impossible', description: apiErrorMessage(error), color: 'error' })
  }
}

// --- Delete -------------------------------------------------------------------------------------

const toDelete = ref<{ kind: 'file' | 'folder', id: string, name: string } | null>(null)
async function remove() {
  const target = toDelete.value!
  toDelete.value = null
  try {
    await api(`/api/${target.kind === 'file' ? 'files' : 'folders'}/${target.id}`, { method: 'DELETE' })
    await refreshAll()
  }
  catch (error) {
    toast.add({ title: 'Suppression impossible', description: apiErrorMessage(error), color: 'error' })
  }
}

// --- Open / download (the API needs the session token: fetched, then handed to the browser) ----

async function openFile(file: StoredFile, download = false) {
  try {
    const blob = await $fetch<Blob>(`/api/files/${file.id}/content`, {
      baseURL: config.public.apiBase,
      query: download ? { download: 1 } : {},
      headers: { Authorization: auth.authorizationHeader() ?? '' },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(download ? blob : new Blob([blob], { type: file.mimeType }))
    if (download || !file.previewable) {
      const link = Object.assign(document.createElement('a'), { href: url, download: file.name })
      link.click()
    }
    else {
      window.open(url, '_blank', 'noopener')
    }
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  }
  catch (error) {
    toast.add({ title: 'Ouverture impossible', description: apiErrorMessage(error), color: 'error' })
  }
}

// --- Share --------------------------------------------------------------------------------------

const sharing = ref<{ type: 'file' | 'folder', id: string, name: string } | null>(null)

function folderMenu(folder: Folder): DropdownMenuItem[][] {
  return [[
    { label: 'Partager', icon: 'i-lucide-share-2', onSelect: () => (sharing.value = { type: 'folder', id: folder.id, name: folder.name }) },
    { label: 'Renommer', icon: 'i-lucide-pencil', onSelect: () => (naming.value = { kind: 'rename-folder', id: folder.id, name: folder.name }) },
    { label: 'Déplacer', icon: 'i-lucide-folder-input', onSelect: () => startMove({ kind: 'folder', id: folder.id, name: folder.name }) },
  ], [
    { label: 'Supprimer', icon: 'i-lucide-trash-2', color: 'error', onSelect: () => (toDelete.value = { kind: 'folder', id: folder.id, name: folder.name }) },
  ]]
}

function fileMenu(file: StoredFile): DropdownMenuItem[][] {
  return [[
    { label: 'Télécharger', icon: 'i-lucide-download', onSelect: () => openFile(file, true) },
    { label: 'Partager', icon: 'i-lucide-share-2', onSelect: () => (sharing.value = { type: 'file', id: file.id, name: file.name }) },
    { label: 'Renommer', icon: 'i-lucide-pencil', onSelect: () => (naming.value = { kind: 'rename-file', id: file.id, name: file.name }) },
    { label: 'Déplacer', icon: 'i-lucide-folder-input', onSelect: () => startMove({ kind: 'file', id: file.id, name: file.name }) },
  ], [
    { label: 'Supprimer', icon: 'i-lucide-trash-2', color: 'error', onSelect: () => (toDelete.value = { kind: 'file', id: file.id, name: file.name }) },
  ]]
}

const breadcrumb = computed(() => [
  { label: 'Mes fichiers', icon: 'i-lucide-house', onClick: () => open(null) },
  ...(current.value?.path ?? []).map(p => ({ label: p.name, onClick: () => open(p.id) })),
])
const empty = computed(() => !folders.value.length && !files.value.length && status.value !== 'pending')
</script>

<template>
  <UDashboardPanel id="files">
    <template #header>
      <UDashboardNavbar title="Mes fichiers">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UInput v-model="search" icon="i-lucide-search" placeholder="Rechercher un fichier…" class="hidden w-56 sm:block" data-testid="search" />
          <UButton icon="i-lucide-folder-plus" label="Nouveau dossier" color="neutral" variant="outline" class="hidden sm:inline-flex" data-testid="new-folder" @click="naming = { kind: 'new-folder', name: '' }" />
          <UButton icon="i-lucide-upload" label="Déposer" data-testid="upload" @click="input?.click()" />
          <input ref="input" type="file" multiple class="hidden" data-testid="file-input" @change="upload(($event.target as HTMLInputElement).files ?? []); ($event.target as HTMLInputElement).value = ''">
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div
        class="relative flex min-h-full flex-col gap-4"
        @dragover.prevent="dragging = true"
        @dragleave.self="dragging = false"
        @drop.prevent="onDrop"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <UBreadcrumb v-if="!search.trim()" :items="breadcrumb" data-testid="breadcrumb" />
          <p v-else class="text-sm text-muted">
            Résultats pour « {{ search }} » dans tous vos dossiers
          </p>
          <div v-if="usage" class="flex w-56 flex-col gap-1 text-xs text-muted" data-testid="usage">
            <UProgress :model-value="usage.quota ? (100 * usage.used) / usage.quota : 0" size="xs" :color="usage.used / usage.quota > 0.9 ? 'warning' : 'primary'" />
            {{ formatSize(usage.used) }} utilisés sur {{ formatSize(usage.quota) }}
          </div>
        </div>

        <UAlert v-if="uploading.length" color="info" variant="subtle" icon="i-lucide-loader-circle" :ui="{ icon: 'animate-spin' }" :title="`Dépôt de ${uploading.length} fichier(s)…`" :description="uploading.map(u => u.name).join(', ')" />

        <div v-if="folders.length && !search.trim()" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" data-testid="folders">
          <div
            v-for="folder in folders"
            :key="folder.id"
            class="group flex items-center gap-2 rounded-lg border border-default p-3 transition hover:border-primary/40 hover:bg-elevated/50"
          >
            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-start" @click="open(folder.id)">
              <UIcon name="i-lucide-folder" class="size-6 shrink-0 text-primary" />
              <span class="truncate font-medium text-highlighted">{{ folder.name }}</span>
            </button>
            <UDropdownMenu :items="folderMenu(folder)">
              <UButton icon="i-lucide-ellipsis-vertical" color="neutral" variant="ghost" size="xs" :aria-label="`Actions sur ${folder.name}`" />
            </UDropdownMenu>
          </div>
        </div>

        <UCard v-if="files.length" :ui="{ body: 'p-0 sm:p-0' }">
          <ul class="divide-y divide-default" data-testid="file-list">
            <li v-for="file in files" :key="file.id" class="flex items-center gap-3 px-4 py-2.5">
              <UIcon :name="fileIcon(file.mimeType)" class="size-5 shrink-0 text-muted" />
              <button type="button" class="min-w-0 flex-1 truncate text-start font-medium text-highlighted hover:underline" @click="openFile(file)">
                {{ file.name }}
              </button>
              <span class="hidden w-24 text-right text-xs text-muted tabular-nums sm:block">{{ formatSize(file.size) }}</span>
              <span class="hidden w-32 text-right text-xs text-muted md:block">{{ formatDate(file.updatedAt ?? file.createdAt) }}</span>
              <UDropdownMenu :items="fileMenu(file)">
                <UButton icon="i-lucide-ellipsis-vertical" color="neutral" variant="ghost" size="xs" :aria-label="`Actions sur ${file.name}`" />
              </UDropdownMenu>
            </li>
          </ul>
        </UCard>

        <div v-if="empty" class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-default p-12 text-center text-muted" data-testid="empty">
          <UIcon name="i-lucide-cloud-upload" class="size-10 text-primary" />
          <p>{{ search.trim() ? 'Aucun fichier ne correspond.' : 'Ce dossier est vide. Glissez-déposez des fichiers ici.' }}</p>
          <UButton v-if="!search.trim()" label="Choisir des fichiers" icon="i-lucide-upload" @click="input?.click()" />
        </div>

        <div v-if="dragging" class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl border-2 border-dashed border-primary bg-primary/5 text-primary">
          <span class="flex items-center gap-2 font-medium"><UIcon name="i-lucide-cloud-upload" class="size-6" />Déposez pour ajouter à ce dossier</span>
        </div>
      </div>

      <UModal :open="naming !== null" :title="naming?.kind === 'new-folder' ? 'Nouveau dossier' : 'Renommer'" @update:open="(value: boolean) => { if (!value) naming = null }">
        <template #body>
          <form v-if="naming" id="name-form" @submit.prevent="saveName">
            <UInput v-model="naming.name" class="w-full" autofocus data-testid="name-input" />
          </form>
        </template>
        <template #footer>
          <div class="flex w-full justify-end gap-2">
            <UButton label="Annuler" color="neutral" variant="ghost" @click="naming = null" />
            <UButton type="submit" form="name-form" label="Enregistrer" />
          </div>
        </template>
      </UModal>

      <UModal :open="moving !== null" :title="`Déplacer « ${moving?.name} »`" @update:open="(value: boolean) => { if (!value) moving = null }">
        <template #body>
          <USelectMenu v-model="destination" :items="destinations" value-key="value" class="w-full" />
        </template>
        <template #footer>
          <div class="flex w-full justify-end gap-2">
            <UButton label="Annuler" color="neutral" variant="ghost" @click="moving = null" />
            <UButton label="Déplacer" @click="move" />
          </div>
        </template>
      </UModal>

      <UModal :open="toDelete !== null" :title="`Supprimer « ${toDelete?.name} » ?`" :description="toDelete?.kind === 'folder' ? 'Le dossier, ses sous-dossiers et tous leurs fichiers seront supprimés définitivement, ainsi que leurs liens de partage.' : 'Le fichier et ses liens de partage seront supprimés définitivement.'" @update:open="(value: boolean) => { if (!value) toDelete = null }">
        <template #footer>
          <div class="flex w-full justify-end gap-2">
            <UButton label="Annuler" color="neutral" variant="ghost" @click="toDelete = null" />
            <UButton label="Supprimer" color="error" @click="remove" />
          </div>
        </template>
      </UModal>

      <FilesShareModal :target="sharing" @close="sharing = null" />
    </template>
  </UDashboardPanel>
</template>
