import { SERVICE_KINDS } from '../constants/serviceKinds.js'

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

/** What a service is called wherever it is shown: its headline, else its kind; `t` is the page's. */
export function serviceName(service, t) {
  return service.headline || t(SERVICE_KINDS[service.kind].labelKey)
}
