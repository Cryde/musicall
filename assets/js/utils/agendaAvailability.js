/**
 * The words for an agenda entry's availability (#1000), shared by the agenda list, the entry drawer
 * and the member rows so the three never disagree on what `absent` or a null answer is called.
 */

const ANSWER_LABELS = {
  yes: 'Disponible',
  no: 'Indisponible',
  absent: 'Absent',
  pending: 'Sans réponse'
}

/** A member's state as the API sends it: `yes`, `no`, `absent` or null for no answer yet. */
export function availabilityAnswerLabel(answer) {
  return ANSWER_LABELS[answer ?? 'pending'] ?? ANSWER_LABELS.pending
}

/**
 * « 3 dispos · 1 indispo · 2 sans réponse », leaving out the counts that are zero. Null when there
 * is nothing to say, so a caller can hide the line.
 */
export function availabilitySummary(totals) {
  if (!totals) return null

  const parts = []
  if (totals.yes > 0) parts.push(`${totals.yes} dispo${totals.yes > 1 ? 's' : ''}`)
  if (totals.no > 0) parts.push(`${totals.no} indispo${totals.no > 1 ? 's' : ''}`)
  if (totals.absent > 0) parts.push(`${totals.absent} absent${totals.absent > 1 ? 's' : ''}`)
  if (totals.pending > 0) parts.push(`${totals.pending} sans réponse`)

  return parts.length > 0 ? parts.join(' · ') : null
}
