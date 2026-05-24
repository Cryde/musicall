const TASK_STATUS_LABELS = {
  todo: 'À faire',
  in_progress: 'En cours',
  done: 'Terminé'
}

const FINANCE_STATUS_LABELS = {
  planned: 'Prévu',
  committed: 'Engagé',
  paid: 'Payé'
}

function taskStatus(value) {
  return TASK_STATUS_LABELS[value] ?? value
}

function financeStatus(value) {
  return FINANCE_STATUS_LABELS[value] ?? value
}

function formatAmount(cents) {
  if (cents === null || cents === undefined) {
    return '—'
  }
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100)
}

const SENTENCES = {
  // Tasks
  'task.status_changed': (a) => {
    return `a changé le statut de ${taskStatus(a.payload?.from)} à ${taskStatus(a.payload?.to)}`
  },
  'task.due_date_changed': () => "a modifié la date d'échéance",
  'task.category_changed': () => 'a modifié la catégorie',
  'task.assignee_added': (a) => `a assigné ${a.payload?.assignee_username ?? 'un membre'}`,
  'task.assignee_removed': (a) => `a retiré ${a.payload?.assignee_username ?? 'un membre'}`,
  'task.task_archived': () => 'a archivé la tâche',
  'task.task_unarchived': () => "a sorti la tâche de l'archive",
  'task.comment_added': () => 'a ajouté un commentaire',
  'task.comment_edited': () => 'a modifié un commentaire',
  'task.comment_deleted': () => 'a supprimé un commentaire',
  'task.mention': (a) => `a mentionné ${a.payload?.mentioned_username ?? 'un membre'}`,

  // Agenda
  'agenda.entry_created': (a) => `a créé l'événement « ${a.payload?.title ?? 'Sans titre'} »`,
  'agenda.entry_deleted': (a) => `a supprimé l'événement « ${a.payload?.title ?? 'Sans titre'} »`,
  'agenda.title_changed': (a) =>
    `a renommé l'événement de « ${a.payload?.from} » à « ${a.payload?.to} »`,
  'agenda.description_changed': () => "a modifié la description de l'événement",
  'agenda.location_changed': (a) =>
    a.payload?.to ? `a changé le lieu en « ${a.payload.to} »` : 'a effacé le lieu',
  'agenda.event_datetime_changed': () => "a modifié la date de début de l'événement",
  'agenda.end_datetime_changed': () => "a modifié la date de fin de l'événement",
  'agenda.is_all_day_changed': (a) =>
    a.payload?.to ? "a basculé l'événement en journée entière" : 'a quitté la journée entière',
  'agenda.occurrence_cancelled': (a) =>
    `a annulé l'occurrence du ${a.payload?.occurrence_date ?? '?'} de « ${a.payload?.title ?? 'Sans titre'} »`,
  'agenda.series_truncated': (a) =>
    `a tronqué la série « ${a.payload?.title ?? 'Sans titre'} » à partir du ${a.payload?.from_occurrence_date ?? '?'}`,

  // Notes
  'notes.note_created': (a) => `a créé la note « ${a.payload?.title ?? 'Sans titre'} »`,
  'notes.note_deleted': (a) => `a supprimé la note « ${a.payload?.title ?? 'Sans titre'} »`,
  'notes.note_renamed': (a) => `a renommé la note de « ${a.payload?.from} » à « ${a.payload?.to} »`,
  'notes.note_emoji_changed': () => "a modifié l'emoji de la note",
  'notes.note_content_updated': () => 'a modifié le contenu de la note',
  'notes.note_file_attached': (a) =>
    `a attaché le fichier « ${a.payload?.original_name ?? '—'} » à une note`,
  // No notes.note_file_detached — note detach is automatic cleanup when an
  // image is removed from the note body; the parallel note_content_updated
  // activity already covers it in the Notes feed.

  // Finance — entries
  'finance.entry_created': (a) =>
    `a créé l'entrée « ${a.payload?.label ?? '—'} » (${formatAmount(a.payload?.amount)})`,
  'finance.entry_deleted': (a) => `a supprimé l'entrée « ${a.payload?.label ?? '—'} »`,
  'finance.entry_status_changed': (a) =>
    `a changé le statut de ${financeStatus(a.payload?.from)} à ${financeStatus(a.payload?.to)}`,
  'finance.entry_label_changed': (a) =>
    `a renommé l'entrée de « ${a.payload?.from} » à « ${a.payload?.to} »`,
  'finance.entry_amount_changed': (a) =>
    `a changé le montant de ${formatAmount(a.payload?.from)} à ${formatAmount(a.payload?.to)}`,
  'finance.entry_date_changed': () => "a modifié la date de l'entrée",
  'finance.entry_category_changed': (a) =>
    `a déplacé l'entrée vers « ${a.payload?.to_name ?? '—'} »`,

  // Finance — splits
  'finance.split_added': (a) =>
    `a ajouté une répartition de ${formatAmount(a.payload?.amount)} pour ${a.payload?.member_username ?? 'un membre'}`,
  'finance.split_removed': (a) =>
    `a retiré la répartition de ${a.payload?.member_username ?? 'un membre'}`,

  // Finance — categories
  'finance.category_created': (a) => `a créé la catégorie « ${a.payload?.name ?? '—'} »`,
  'finance.category_renamed': (a) =>
    `a renommé la catégorie de « ${a.payload?.from} » à « ${a.payload?.to} »`,
  'finance.category_deleted': (a) => `a supprimé la catégorie « ${a.payload?.name ?? '—'} »`,
  'finance.categories_bootstrapped': (a) =>
    `a initialisé les ${a.payload?.count ?? '?'} catégories par défaut`,

  // Finance — recurrences
  'finance.recurrence_created': (a) =>
    `a créé une récurrence « ${a.payload?.label ?? '—'} » (${a.payload?.generated_entries ?? 0} entrées générées)`,
  'finance.recurrence_updated': (a) =>
    `a modifié la récurrence (${(a.payload?.changed_fields ?? []).join(', ')})`,
  'finance.recurrence_started': () => 'a réactivé la récurrence',
  'finance.recurrence_stopped': () => 'a désactivé la récurrence',
  'finance.recurrence_end_date_changed': () => 'a modifié la date de fin de la récurrence',
  'finance.recurrence_deleted': (a) => `a supprimé la récurrence « ${a.payload?.label ?? '—'} »`,

  // Setlist — songs (repertoire)
  'setlist.song_added': (a) => `a ajouté la chanson « ${a.payload?.title ?? '—'} » au répertoire`,
  'setlist.song_updated': (a) => `a modifié la chanson « ${a.payload?.title ?? '—'} »`,
  'setlist.song_archived': (a) =>
    `a retiré la chanson « ${a.payload?.title ?? '—'} » du répertoire`,
  'setlist.song_unarchived': (a) =>
    `a réintégré la chanson « ${a.payload?.title ?? '—'} » au répertoire`,
  'setlist.song_file_attached': (a) =>
    `a attaché le fichier « ${a.payload?.original_name ?? '—'} » à une chanson`,
  'setlist.song_file_detached': (a) =>
    `a détaché le fichier « ${a.payload?.original_name ?? '—'} » d'une chanson`,

  // Setlist — setlists
  'setlist.setlist_created': (a) => `a créé la setlist « ${a.payload?.name ?? '—'} »`,
  'setlist.setlist_renamed': (a) => `a renommé la setlist en « ${a.payload?.name ?? '—'} »`,
  'setlist.setlist_duplicated': (a) => `a dupliqué la setlist « ${a.payload?.name ?? '—'} »`,
  'setlist.setlist_archived': (a) => `a archivé la setlist « ${a.payload?.name ?? '—'} »`,
  'setlist.setlist_unarchived': (a) => `a réintégré la setlist « ${a.payload?.name ?? '—'} »`,

  // Setlist — items
  'setlist.setlist_item_added': (a) =>
    a.payload?.label
      ? `a ajouté « ${a.payload.label} » à une setlist`
      : 'a ajouté un titre à une setlist',
  'setlist.setlist_item_removed': () => "a retiré un titre d'une setlist",
  'setlist.setlist_item_reordered': (a) =>
    a.payload?.count
      ? `a réordonné les ${a.payload.count} titres d'une setlist`
      : "a réordonné les titres d'une setlist",
  'setlist.setlist_item_updated': () => "a modifié un titre d'une setlist",

  // Setlist — files
  'setlist.setlist_file_attached': (a) =>
    `a attaché le fichier « ${a.payload?.original_name ?? '—'} » à une setlist`,
  'setlist.setlist_file_detached': (a) =>
    `a détaché le fichier « ${a.payload?.original_name ?? '—'} » d'une setlist`,

  // Settings — band
  'settings.band_created': (a) => `a créé le Band Space « ${a.payload?.name ?? '—'} »`,

  // Settings — membership
  'settings.member_role_changed': (a) =>
    `a changé le rôle de ${a.payload?.target_username ?? 'un membre'} de ${a.payload?.from} à ${a.payload?.to}`,
  'settings.member_removed': (a) =>
    `a exclu ${a.payload?.target_username ?? 'un membre'} du Band Space`,
  'settings.member_left': () => 'a quitté le Band Space',

  // Settings — invitations
  'settings.invitation_sent': (a) => `a invité ${a.payload?.email ?? 'un utilisateur'}`,
  'settings.invitation_accepted': (a) =>
    `a rejoint le Band Space (invitation à ${a.payload?.email})`,
  'settings.invitation_declined': (a) => `a refusé l'invitation pour ${a.payload?.email}`,
  'settings.invitation_revoked': (a) => `a annulé l'invitation pour ${a.payload?.email}`,
  'settings.invitation_expired': (a) =>
    `l'invitation pour ${a.payload?.email ?? 'un utilisateur'} a expiré`
}

export function activitySentence(activity) {
  const key = `${activity.module}.${activity.type}`
  const fn = SENTENCES[key]
  if (fn) {
    return fn(activity)
  }
  return `a effectué l'action « ${activity.type} » sur le module ${activity.module}`
}
