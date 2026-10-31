/** Mirrors `App\Enum\BandSpace\BandSpaceModule`, with the names the members read in the sidebar. */
export const BAND_SPACE_MODULE_LABELS = Object.freeze({
  file: 'Fichiers',
  task: 'Tâches',
  finance: 'Finances',
  agenda: 'Agenda',
  notes: 'Notes',
  settings: 'Paramètres',
  setlist: 'Setlists',
  rider: 'Tech riders'
})

/** Mirrors `BandSpaceFileQuotaService::APPROACHING_LIMIT_RATIO`, where the members get warned. */
const APPROACHING_LIMIT_RATIO = 0.8

/** The tag severity of a space's storage against its quota: full, close to it, or fine. */
export function quotaSeverity(bytes, quotaBytes) {
  if (quotaBytes <= 0 || bytes >= quotaBytes) return 'danger'
  if (bytes / quotaBytes >= APPROACHING_LIMIT_RATIO) return 'warn'
  return 'secondary'
}
