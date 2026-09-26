<script setup lang="ts">
import type { Share } from '~/types/api'

/** Creates a share link for a file or a folder, optionally emailed to recipients through Rocket Mailer. */
const props = defineProps<{ target: { type: 'file' | 'folder', id: string, name: string } | null }>()
const emit = defineEmits<{ close: [], created: [share: Share] }>()

const api = useApi()
const toast = useToast()
const EMPTY = { password: '', expiresAt: '', maxDownloads: null as number | null, recipients: [] as string[], message: '' }
const form = reactive({ ...EMPTY })
const created = ref<Share | null>(null)
const saving = ref(false)

watch(() => props.target, () => {
  Object.assign(form, structuredClone(EMPTY))
  created.value = null
})

async function submit() {
  if (!props.target) return
  saving.value = true
  try {
    created.value = await api<Share>('/api/shares', {
      method: 'POST',
      body: {
        [props.target.type]: `/api/${props.target.type === 'file' ? 'files' : 'folders'}/${props.target.id}`,
        password: form.password || null,
        expiresAt: form.expiresAt ? new Date(form.expiresAt).toISOString() : null,
        maxDownloads: form.maxDownloads || null,
        recipients: form.recipients,
        message: form.message || null,
      },
    })
    emit('created', created.value)
  }
  catch (error) {
    toast.add({ title: 'Partage impossible', description: apiErrorMessage(error), color: 'error' })
  }
  finally {
    saving.value = false
  }
}

async function copy() {
  await navigator.clipboard.writeText(shareUrl(created.value!.token))
  toast.add({ title: 'Lien copié', color: 'success', duration: 1500 })
}
</script>

<template>
  <UModal :open="target !== null" :title="`Partager « ${target?.name} »`" @update:open="(value: boolean) => { if (!value) emit('close') }">
    <template #body>
      <div v-if="created" class="flex flex-col gap-3" data-testid="share-created">
        <UAlert color="success" variant="subtle" icon="i-lucide-link" title="Lien de partage créé" :description="created.recipients.length ? (created.notificationStatus === 'queued' ? `Un email part vers ${created.recipients.join(', ')} via Rocket Mailer.` : created.notificationStatus ?? '') : 'Transmettez ce lien aux personnes concernées.'" />
        <div class="flex items-center gap-2">
          <code class="min-w-0 flex-1 break-all rounded bg-elevated p-2 text-sm" data-testid="share-url">{{ shareUrl(created.token) }}</code>
          <UButton icon="i-lucide-copy" color="neutral" variant="outline" aria-label="Copier" @click="copy" />
        </div>
      </div>
      <form v-else id="share-form" class="flex flex-col gap-3" @submit.prevent="submit">
        <UFormField label="Envoyer le lien par email (optionnel)" hint="Via Rocket Mailer, en votre nom">
          <UInputTags v-model="form.recipients" add-on-blur add-on-paste placeholder="client@exemple.com" class="w-full" data-testid="share-recipients" />
        </UFormField>
        <UFormField v-if="form.recipients.length" label="Message">
          <UTextarea v-model="form.message" :rows="2" class="w-full" />
        </UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="Mot de passe (optionnel)">
            <UInput v-model="form.password" type="password" autocomplete="new-password" class="w-full" />
          </UFormField>
          <UFormField label="Téléchargements max.">
            <UInputNumber v-model="form.maxDownloads" :min="1" placeholder="Illimité" class="w-full" />
          </UFormField>
        </div>
        <UFormField label="Expire le (optionnel)">
          <UInput v-model="form.expiresAt" type="datetime-local" class="w-full" />
        </UFormField>
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton :label="created ? 'Fermer' : 'Annuler'" color="neutral" variant="ghost" @click="emit('close')" />
        <UButton v-if="!created" type="submit" form="share-form" label="Créer le lien" icon="i-lucide-link" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>
