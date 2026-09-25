<script setup lang="ts">
import type { PublicShare } from '~/types/api'

/** Public page of a share link: no account needed. Downloads are form POSTs (the password never goes into a URL). */
definePageMeta({ layout: 'bare' })
const app = useAppConfig().rocket
useHead({ title: `Partage · ${app.name}` })

const route = useRoute()
const config = useRuntimeConfig()
const token = computed(() => String(route.params.token))
const endpoint = computed(() => `${config.public.apiBase}/api/public/shares/${token.value}`)

const { data: share, error } = await useAsyncData('public-share', () => $fetch<PublicShare>(endpoint.value))
const password = ref('')
const files = ref(share.value?.files ?? [])
const unlocking = ref(false)
const unlockError = ref<string | null>(null)

async function unlock() {
  unlocking.value = true
  unlockError.value = null
  try {
    const body = new FormData()
    body.append('password', password.value)
    body.append('list', '1')
    files.value = (await $fetch<{ files: NonNullable<PublicShare['files']> }>(`${endpoint.value}/download`, { method: 'POST', body })).files
  }
  catch (e) {
    unlockError.value = apiErrorMessage(e)
  }
  finally {
    unlocking.value = false
  }
}

const errorMessage = computed(() => {
  const data = (error.value as { data?: { detail?: string } } | null)?.data
  return data?.detail ?? 'Ce lien n’existe pas ou a été supprimé.'
})
const locked = computed(() => share.value?.passwordProtected && !files.value.length)
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-elevated/40 p-4">
    <UCard class="w-full max-w-lg" data-testid="public-share">
      <template #header>
        <div class="flex items-center gap-2 text-lg font-semibold">
          <UIcon :name="app.icon" class="size-6 text-primary" />
          {{ app.name }}
        </div>
      </template>

      <UAlert v-if="error || !share" color="warning" variant="subtle" icon="i-lucide-link-2-off" title="Lien indisponible" :description="errorMessage" />

      <div v-else class="flex flex-col gap-4">
        <div>
          <p class="flex items-center gap-2 text-lg font-semibold text-highlighted">
            <UIcon :name="share.type === 'folder' ? 'i-lucide-folder' : 'i-lucide-file'" class="size-5 text-primary" />
            {{ share.name }}
          </p>
          <p v-if="share.sharedBy" class="text-sm text-muted">
            Partagé par {{ share.sharedBy }}<template v-if="share.expiresAt">
              · disponible jusqu’au {{ formatDate(share.expiresAt) }}
            </template>
          </p>
        </div>
        <blockquote v-if="share.message" class="border-l-2 border-primary pl-3 text-sm italic">
          {{ share.message }}
        </blockquote>

        <form v-if="locked" class="flex flex-col gap-3" @submit.prevent="unlock">
          <UFormField label="Mot de passe" help="Ce partage est protégé : demandez le mot de passe à la personne qui vous l’a envoyé.">
            <UInput v-model="password" type="password" class="w-full" autofocus data-testid="share-password" />
          </UFormField>
          <UAlert v-if="unlockError" color="error" variant="subtle" :description="unlockError" />
          <UButton type="submit" label="Accéder" :loading="unlocking" block />
        </form>

        <ul v-else class="divide-y divide-default rounded-lg border border-default" data-testid="public-files">
          <li v-for="file in files" :key="file.id" class="flex items-center gap-3 px-3 py-2">
            <UIcon :name="fileIcon(file.mimeType)" class="size-5 shrink-0 text-muted" />
            <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
            <span class="text-xs text-muted tabular-nums">{{ formatSize(file.size) }}</span>
            <form method="post" :action="`${endpoint}/download`" target="_blank">
              <input type="hidden" name="password" :value="password">
              <input type="hidden" name="file" :value="share.type === 'folder' ? file.id : ''">
              <UButton type="submit" icon="i-lucide-download" size="xs" label="Télécharger" />
            </form>
          </li>
        </ul>
        <p v-if="share.remainingDownloads !== null" class="text-xs text-muted">
          {{ share.remainingDownloads }} téléchargement(s) restant(s).
        </p>
      </div>
    </UCard>
  </div>
</template>
