export const ADMIN_MODULES = [
  {
    key: 'publications',
    label: 'Publications',
    icon: 'pi-file-edit',
    route: 'admin_publications_index',
    description: 'Modération des publications, galeries et commentaires',
    color: '#6366f1'
  },
  {
    key: 'directory',
    label: 'Annuaire',
    icon: 'pi-id-card',
    route: 'admin_annuaire_index',
    description: 'Annonces musiciens, professeurs et autres profils',
    color: '#22c55e'
  },
  {
    key: 'forum',
    label: 'Forum',
    icon: 'pi-comments',
    route: 'admin_forum_index',
    description: 'Sujets, posts et modération du forum',
    color: '#06b6d4'
  },
  {
    key: 'band-space',
    label: 'Band Space',
    icon: 'pi-objects-column',
    route: 'admin_band_space_coming_soon',
    description: 'Espaces de groupe et leurs activités',
    color: '#f59e0b'
  },
  {
    key: 'users',
    label: 'Utilisateurs',
    icon: 'pi-users',
    route: 'admin_users_dashboard',
    description: 'Gestion des comptes et activité utilisateurs',
    color: '#ec4899'
  }
]
