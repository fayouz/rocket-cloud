// Types of the Rocket core (users, applications, dashboard…), then those of this application.
import type { Tracked } from '#rocket/types/api'

export type * from '#rocket/types/api'

export interface FolderRef {
  id: string
  name: string
}

export interface Folder extends Tracked {
  id: string
  name: string
  /** IRI of the parent folder, null at the root. */
  parent: string | null
  /** From the root to this folder. */
  path: FolderRef[]
}

export interface StoredFile extends Tracked {
  id: string
  name: string
  /** IRI of its folder, null at the root. */
  folder: string | null
  size: number
  mimeType: string
  sha256: string
  previewable: boolean
}

export interface StorageUsage {
  used: number
  quota: number
  maxFileSize: number
  files: number
}

export interface Share {
  id: string
  token: string
  file: string | null
  folder: string | null
  targetName: string
  expiresAt: string | null
  passwordProtected: boolean
  maxDownloads: number | null
  downloadCount: number
  recipients: string[]
  message: string | null
  /** null: nobody to notify; "queued", "sent", or the error. */
  notificationStatus: string | null
  createdAt: string
  lastDownloadAt: string | null
}

/** GET /api/public/shares/{token} */
export interface PublicShare {
  name: string
  type: 'file' | 'folder'
  sharedBy: string | null
  message: string | null
  expiresAt: string | null
  passwordProtected: boolean
  remainingDownloads: number | null
  /** Listed once the password is known. */
  files?: { id: string, name: string, size: number, mimeType: string }[]
}
