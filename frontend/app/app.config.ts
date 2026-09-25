/**
 * Identity of the application and its own menu entries: the rest of the interface (layout, dashboard,
 * administration pages) is shared by every Rocket application.
 */
export default defineAppConfig({
  ui: {
    colors: {
      primary: 'sky',
      neutral: 'zinc',
    },
  },
  rocket: {
    id: 'cloud',
    name: 'Rocket Cloud',
    icon: 'i-lucide-cloud',
    // Login page subtitle.
    tagline: 'Vos fichiers, rangés, partagés et disponibles pour vos applications.',
    // Main menu: the domain pages ("label" entries start a group).
    navigation: [
      { label: 'Fichiers', type: 'label' },
      { label: 'Mes fichiers', icon: 'i-lucide-folder', to: '/files' },
      { label: 'Mes partages', icon: 'i-lucide-share-2', to: '/shares' },
    ] as { label: string, icon?: string, to?: string, type?: 'label', exact?: boolean, admin?: boolean }[],
    // Share links are opened by anyone who has the link.
    publicPaths: ['/s/'],
    // Extra entries of the Administration menu.
    adminNavigation: [] as { label: string, icon: string, to: string }[],
    // "Services & raccourcis" of the dashboard, besides the documentation, changelog and API.
    shortcuts: [] as { label: string, description: string, icon: string, to: string, admin?: boolean }[],
    // Hero banner of the dashboard: one quote per day.
    quotes: [
      ['Une place pour chaque chose, et chaque chose à sa place.', 'Proverbe'],
      ['Le partage est la seule richesse qui augmente quand on la donne.', 'Proverbe'],
      ['La simplicité est la sophistication suprême.', 'Léonard de Vinci'],
    ] as [string, string][],
  },
})
