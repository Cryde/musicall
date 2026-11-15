/** Where a service stands, for its card: online, a draft ready to go online, or a draft still missing a part. */
export function serviceStage(service) {
  if (service.status === 'published') return 'published'
  return service.missing_to_publish.length ? 'incomplete' : 'ready'
}

/** The kinds the member does not offer yet, in the order given: « Ajouter un service » shows one card each. */
export function kindsToAdd(kinds, services) {
  const offered = new Set(services.map((service) => service.kind))
  return kinds.filter((kind) => !offered.has(kind))
}
