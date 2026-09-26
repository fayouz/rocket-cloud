/** Icon of a file from its type. */
export function fileIcon(mimeType: string): string {
  if (mimeType.startsWith('image/')) return 'i-lucide-file-image'
  if (mimeType.startsWith('video/')) return 'i-lucide-file-video'
  if (mimeType.startsWith('audio/')) return 'i-lucide-file-audio'
  if (mimeType === 'application/pdf') return 'i-lucide-file-text'
  if (/spreadsheet|excel|csv/.test(mimeType)) return 'i-lucide-file-spreadsheet'
  if (/zip|compressed|tar|rar|7z/.test(mimeType)) return 'i-lucide-file-archive'
  if (/word|document|text\//.test(mimeType)) return 'i-lucide-file-text'
  return 'i-lucide-file'
}

/** "/api/folders/<id>" → "<id>". */
export function iriId(iri: string | null | undefined): string | null {
  return iri ? iri.split('/').pop() ?? null : null
}

/** Public URL of a share link. */
export function shareUrl(token: string): string {
  return `${window.location.origin}/s/${token}`
}
