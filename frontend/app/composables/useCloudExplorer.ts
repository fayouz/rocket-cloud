import type { ExplorerAdapter, ExplorerItem, ExplorerLocation } from '#file-explorer'
import type { Folder, StorageUsage, StoredFile } from '~/types/api'

/** The user's space is the only space of the explorer. */
export const CLOUD_SPACE = 'me'

/**
 * Adapter of @rocket/file-explorer on the Rocket Cloud API: folders (/api/folders) and files (/api/files) of the
 * user's private space. Contents need the session's Authorization header: they are fetched, then handed to the browser
 * as blob: addresses. "Partager" is an application action (the page opens its share window).
 */
export function useCloudExplorer(usage: Ref<StorageUsage | null | undefined>): ExplorerAdapter {
  const api = useApi()
  const auth = useAuth()
  const config = useRuntimeConfig()
  const folderIri = (id: ExplorerLocation['folder']) => (id ? `/api/folders/${id}` : null)
  const idOf = (iri: string | null) => (iri ? iri.split('/').pop()! : null)
  const fileItem = (f: StoredFile, where?: string): ExplorerItem => ({
    id: f.id, kind: 'file', name: f.name, size: f.size, updatedAt: f.updatedAt ?? f.createdAt, parentId: idOf(f.folder), spaceId: CLOUD_SPACE,
    previewable: f.previewable, where,
  })
  const folderItem = (f: Folder): ExplorerItem => ({ id: f.id, kind: 'folder', name: f.name, updatedAt: f.updatedAt ?? f.createdAt, parentId: idOf(f.parent), spaceId: CLOUD_SPACE })

  return {
    rootLabel: 'Mes fichiers',
    async list(location, query) {
      const space = { id: CLOUD_SPACE, name: 'Mes fichiers', icon: 'i-lucide-house' }
      // Search: files of every folder whose name matches (the API searches file names)
      if (query.q) {
        const files = await api<StoredFile[]>('/api/files', { query: { 'name': query.q, 'itemsPerPage': 500, 'order[name]': 'asc' } })
        return { space, spaces: [space], items: files.map(f => fileItem(f, f.folder ? 'dans un dossier' : 'Mes fichiers')) }
      }
      const folder = location.folder ? String(location.folder) : null
      const [current, folders, files] = await Promise.all([
        folder ? api<Folder>(`/api/folders/${folder}`) : Promise.resolve(null),
        api<Folder[]>('/api/folders', { query: folder ? { parent: folder, itemsPerPage: 500 } : { 'exists[parent]': false, 'itemsPerPage': 500 } }),
        api<StoredFile[]>('/api/files', { query: { ...(folder ? { folder } : { 'exists[folder]': false }), 'itemsPerPage': 500, 'order[name]': 'asc' } }),
      ])
      return { space, spaces: [space], path: current?.path ?? [], items: [...folders.map(folderItem), ...files.map(f => fileItem(f))] }
    },
    async createFolder(location, name) {
      await api('/api/folders', { method: 'POST', body: { name, parent: folderIri(location.folder) } })
    },
    async upload(location, files) {
      const errors: string[] = []
      for (const file of files) {
        if (usage.value && file.size > usage.value.maxFileSize) {
          errors.push(`${file.name} : trop volumineux (max. ${formatSize(usage.value.maxFileSize)})`)
          continue
        }
        const body = new FormData()
        body.append('file', file)
        if (location.folder) body.append('folder', String(location.folder))
        try {
          await api('/api/files', { method: 'POST', body })
        }
        catch (error) {
          errors.push(`${file.name} : ${apiErrorMessage(error)}`)
        }
      }
      return { errors }
    },
    async rename(item, name) {
      await api(`/api/${item.kind === 'file' ? 'files' : 'folders'}/${item.id}`, { method: 'PATCH', body: { name } })
    },
    async move(items, target) {
      const iri = folderIri(target.folder)
      for (const item of items) {
        await api(`/api/${item.kind === 'file' ? 'files' : 'folders'}/${item.id}`, { method: 'PATCH', body: item.kind === 'file' ? { folder: iri } : { parent: iri } })
      }
    },
    async remove(items) {
      for (const item of items) await api(`/api/${item.kind === 'file' ? 'files' : 'folders'}/${item.id}`, { method: 'DELETE' })
    },
    async resolveFileUrl(item, download) {
      const blob = await $fetch<Blob>(`/api/files/${item.id}/content`, {
        baseURL: config.public.apiBase,
        query: download ? { download: 1 } : {},
        headers: { Authorization: auth.authorizationHeader() ?? '' },
        responseType: 'blob',
      })
      return URL.createObjectURL(blob)
    },
    actions: items => (items.length === 1 && items[0]!.kind !== 'space' ? [{ id: 'share', label: 'Partager', icon: 'i-lucide-share-2' }] : []),
    errorMessage: apiErrorMessage,
  }
}
