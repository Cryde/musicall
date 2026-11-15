/**
 * The ground a moderator picks when suspending (#1126), one per ModerationBasis case on the server:
 * the items of the terms' §6, plus illegal content. Pinned by ModerationBasisClientMirrorTest.
 */
export const MODERATION_BASIS_OPTIONS = [
  { value: 'illegal_content', label: 'Contenu illicite au regard de la loi' },
  {
    value: 'objectionable_content',
    label: 'CGU §6 : contenu diffamatoire, haineux, violent ou pornographique'
  },
  { value: 'harassment', label: 'CGU §6 : harcèlement, menaces ou intimidation' },
  { value: 'impersonation', label: "CGU §6 : usurpation d'identité" },
  { value: 'spam', label: 'CGU §6 : spam ou promotion non sollicitée' },
  { value: 'security', label: 'CGU §6 : atteinte à la sécurité du site' },
  { value: 'personal_data', label: 'CGU §6 : collecte de données personnelles' },
  { value: 'intellectual_property', label: 'CGU §6 : violation de propriété intellectuelle' },
  {
    value: 'band_space_storage',
    label: 'CGU §6 : Band Space utilisé comme stockage sans lien avec un groupe'
  },
  { value: 'quota_circumvention', label: 'CGU §6 : contournement des quotas' }
]

/** What the user page calls each moderation email (UserEmailType::moderation() on the server). */
export const MODERATION_EMAIL_LABELS = {
  account_suspended_statement: 'Décision motivée de suspension',
  suspension_lifted: 'Levée de la suspension'
}
