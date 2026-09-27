<script setup lang="ts">
import type { EmbedContext } from '#rocket/composables/useEmbedBridge'
import type { Folder, StoredFile } from '~/types/api'

/**
 * File picker embedded in an application (see public/embed.js): /embed/picker?app=<application>, with optional
 * mode=file|folder, accept=<MIME types or prefixes, comma-separated>, folder=<id to open>.
 * Authentication: an embed token minted by the application for one user (useEmbedBridge of the layer).
 * Tells the host "selected" ({file} or {folder}) and "cancel"; the host may send "refresh".
 */
definePageMeta({ layout: 'bare' })
useHead({ title: 'Rocket Cloud' })

const route = useRoute()
const bridge = useEmbedBridge()
const api = useApi()

const state = ref<'loading' | 'ready' | 'error'>('loading')
const errorMessage = ref('')
const context = ref<EmbedContext | null>(null)
const mode = computed(() => (route.query.mode === 'folder' ? 'folder' : 'file'))
const accept = computed(() => (typeof route.query.accept === 'string' ? route.query.accept.split(',').map(a => a.trim()).filter(Boolean) : []))

const folderId = ref<string | null>(typeof route.query.folder === 'string' ? route.query.folder : null)
const current = ref<Folder | null>(null)
const folders = ref<Folder[]>([])
const files = ref<StoredFile[]>([])
const search = ref('')
const selected = ref<StoredFile | null>(null)
const loading = ref(false)
let stopRefresh: (() => void) | undefined

function accepted(file: StoredFile): boolean {
  return !accept.value.length || accept.value.some(a => file.mimeType === a || (a.endsWith('/') || a.endsWith('*') ? file.mimeType.startsWith(a.replace(/\*$/, '')) : false))
}

async function load() {
  loading.value = true
  selected.value = null
  try {
    const term = search.value.trim()
    const [folder, subfolders, list] = await Promise.all([
      folderId.value ? api<Folder>(`/api/folders/${folderId.value}`) : Promise.resolve(null),
      term ? Promise.resolve([]) : api<Folder[]>('/api/folders', { query: folderId.value ? { parent: folderId.value, itemsPerPage: 500 } : { 'exists[parent]': false, 'itemsPerPage': 500 } }),
      mode.value === 'folder'
        ? Promise.resolve([])
        : api<StoredFile[]>('/api/files', {
            query: {
              ...(term ? { name: term } : folderId.value ? { folder: folderId.value } : { 'exists[folder]': false }),
              // One accepted type: filtered by the API (prefix match); several: filtered here.
              ...(accept.value.length === 1 ? { mimeType: accept.value[0]!.replace(/\*$/, '') } : {}),
              'itemsPerPage': 500,
              'order[name]': 'asc',
            },
          }),
    ])
    current.value = folder
    folders.value = subfolders
    files.value = list.filter(accepted)
  }
  catch (error) {
    errorMessage.value = apiErrorMessage(error)
    state.value = 'error'
  }
  finally {
    loading.value = false
  }
}

function open(id: string | null) {
  search.value = ''
  folderId.value = id
  load()
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 250)
})

function choose() {
  if (mode.value === 'folder') {
    bridge.notify('selected', { folder: { id: current.value?.id ?? null, name: current.value?.name ?? 'Mes fichiers', path: current.value?.path ?? [] } })
    return
  }
  const file = selected.value
  if (!file) return
  bridge.notify('selected', {
    file: { id: file.id, name: file.name, mimeType: file.mimeType, size: file.size, sha256: file.sha256, folder: iriId(file.folder), path: current.value?.path ?? [], updatedAt: file.updatedAt },
  })
}

onMounted(async () => {
  try {
    context.value = await bridge.connect(typeof route.query.app === 'string' ? route.query.app : '')
    stopRefresh = bridge.onHostMessage('refresh', () => load())
    state.value = 'ready'
    await load()
  }
  catch (error) {
    errorMessage.value = apiErrorMessage(error)
    state.value = 'error'
  }
})

onBeforeUnmount(() => {
  stopRefresh?.()
  clearTimeout(searchTimer)
})
</script>

<template>
  <div class="flex flex-col gap-3 p-4">
    <div v-if="state === 'loading'" class="flex items-center gap-2 py-10 text-muted">
      <UIcon name="i-lucide-loader-circle" class="size-5 animate-spin" /> Chargement…
    </div>

    <UAlert v-else-if="state === 'error'" color="error" variant="subtle" icon="i-lucide-shield-alert" title="Fichiers indisponibles" :description="errorMessage" />

    <template v-else>
      <div class="flex flex-wrap items-center gap-2">
        <UBreadcrumb
          :items="[{ label: 'Mes fichiers', icon: 'i-lucide-cloud', onClick: () => open(null) }, ...(current?.path ?? []).map(p => ({ label: p.name, onClick: () => open(p.id) }))]"
          class="min-w-0 flex-1"
        />
        <UInput v-if="mode === 'file'" v-model="search" icon="i-lucide-search" placeholder="Rechercher" size="sm" class="w-48" />
      </div>

      <div class="max-h-[420px] min-h-40 overflow-y-auto rounded-md border border-default" :class="{ 'opacity-60': loading }">
        <button
          v-for="folder in folders"
          :key="folder.id"
          type="button"
          class="flex w-full items-center gap-3 border-b border-default px-3 py-2 text-left last:border-0 hover:bg-elevated"
          @click="open(folder.id)"
        >
          <UIcon name="i-lucide-folder" class="size-5 shrink-0 text-primary" />
          <span class="truncate font-medium">{{ folder.name }}</span>
          <UIcon name="i-lucide-chevron-right" class="ml-auto size-4 text-muted" />
        </button>
        <button
          v-for="file in files"
          :key="file.id"
          type="button"
          class="flex w-full items-center gap-3 border-b border-default px-3 py-2 text-left last:border-0"
          :class="selected?.id === file.id ? 'bg-primary/10' : 'hover:bg-elevated'"
          @click="selected = file"
          @dblclick="selected = file; choose()"
        >
          <UIcon :name="fileIcon(file.mimeType)" class="size-5 shrink-0 text-muted" />
          <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
          <span class="text-xs text-muted">{{ formatSize(file.size) }} · {{ timeAgo(file.updatedAt) }}</span>
        </button>
        <p v-if="!loading && !folders.length && !files.length" class="p-6 text-center text-sm text-muted">
          {{ mode === 'folder' ? 'Aucun sous-dossier.' : accept.length ? 'Aucun fichier de ce type ici.' : 'Ce dossier est vide.' }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <p class="min-w-0 flex-1 truncate text-sm text-muted">
          <template v-if="mode === 'folder'">
            Dossier : <strong>{{ current?.name ?? 'Mes fichiers' }}</strong>
          </template>
          <template v-else-if="selected">
            <strong>{{ selected.name }}</strong>
          </template>
          <template v-else>
            {{ context?.user.displayName }} · via {{ context?.application.name }}
          </template>
        </p>
        <UButton label="Annuler" color="neutral" variant="ghost" @click="bridge.notify('cancel')" />
        <UButton :label="mode === 'folder' ? 'Choisir ce dossier' : 'Choisir'" icon="i-lucide-check" :disabled="mode === 'file' && !selected" @click="choose" />
      </div>
    </template>
  </div>
</template>
