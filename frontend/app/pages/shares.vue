<script setup lang="ts">
import type { Share } from '~/types/api'

const app = useAppConfig().rocket
useHead({ title: `Mes partages · ${app.name}` })

const api = useApi()
const toast = useToast()
const { data: shares, status, refresh } = await useAsyncData('shares', () => api<Share[]>('/api/shares'), { default: () => [] })

function state(share: Share): { label: string, color: 'success' | 'neutral' | 'warning' } {
  if (share.expiresAt && new Date(share.expiresAt) <= new Date()) return { label: 'Expiré', color: 'neutral' }
  if (share.maxDownloads !== null && share.downloadCount >= share.maxDownloads) return { label: 'Épuisé', color: 'neutral' }
  return { label: 'Actif', color: 'success' }
}

function notification(share: Share): string | null {
  if (!share.recipients.length) return null
  if (share.notificationStatus === 'sent') return `Email envoyé à ${share.recipients.join(', ')}`
  if (share.notificationStatus === 'queued') return `Email en cours d’envoi à ${share.recipients.join(', ')}`
  return `Email non envoyé : ${share.notificationStatus}`
}

async function copy(share: Share) {
  await navigator.clipboard.writeText(shareUrl(share.token))
  toast.add({ title: 'Lien copié', color: 'success', duration: 1500 })
}

const toDelete = ref<Share | null>(null)
async function remove() {
  const share = toDelete.value!
  toDelete.value = null
  try {
    await api(`/api/shares/${share.id}`, { method: 'DELETE' })
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Suppression impossible', description: apiErrorMessage(error), color: 'error' })
  }
}
</script>

<template>
  <UDashboardPanel id="shares">
    <template #header>
      <UDashboardNavbar title="Mes partages">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="mx-auto flex w-full max-w-4xl flex-col gap-3">
        <p class="text-sm text-muted">
          Les liens publics que vous avez créés. Supprimer un lien le rend immédiatement inutilisable.
        </p>
        <UCard v-for="share in shares" :key="share.id" data-testid="share">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="flex items-center gap-2 font-medium text-highlighted">
                <UIcon :name="share.folder ? 'i-lucide-folder' : 'i-lucide-file'" class="size-4 text-primary" />
                {{ share.targetName }}
                <UBadge v-bind="state(share)" variant="subtle" size="sm" />
                <UBadge v-if="share.passwordProtected" label="Mot de passe" icon="i-lucide-lock" variant="outline" color="neutral" size="sm" />
              </p>
              <p class="mt-1 text-xs text-muted">
                Créé {{ timeAgo(share.createdAt) }} · {{ share.downloadCount }}{{ share.maxDownloads ? `/${share.maxDownloads}` : '' }} téléchargement(s)<template v-if="share.expiresAt">
                  · expire le {{ formatDate(share.expiresAt) }}
                </template>
              </p>
              <p v-if="notification(share)" class="mt-1 text-xs" :class="share.notificationStatus === 'sent' || share.notificationStatus === 'queued' ? 'text-muted' : 'text-error'">
                {{ notification(share) }}
              </p>
            </div>
            <div class="flex gap-1">
              <UButton icon="i-lucide-copy" label="Copier le lien" color="neutral" variant="outline" size="sm" @click="copy(share)" />
              <UButton icon="i-lucide-trash-2" color="error" variant="ghost" size="sm" aria-label="Supprimer" @click="toDelete = share" />
            </div>
          </div>
        </UCard>
        <UCard v-if="status !== 'pending' && !shares.length">
          <p class="py-6 text-center text-sm text-muted">
            Aucun partage. Dans « Mes fichiers », choisissez « Partager » sur un fichier ou un dossier.
          </p>
        </UCard>
      </div>

      <UModal :open="toDelete !== null" :title="`Supprimer le lien vers « ${toDelete?.targetName} » ?`" description="Les personnes qui l’ont reçu ne pourront plus l’ouvrir." @update:open="(value: boolean) => { if (!value) toDelete = null }">
        <template #footer>
          <div class="flex w-full justify-end gap-2">
            <UButton label="Annuler" color="neutral" variant="ghost" @click="toDelete = null" />
            <UButton label="Supprimer" color="error" @click="remove" />
          </div>
        </template>
      </UModal>
    </template>
  </UDashboardPanel>
</template>
