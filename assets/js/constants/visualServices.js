/**
 * The photographer and videographer kinds (#1411): one page and one form for both, each kind its own words and works
 * layout; their icon, page and search are in SERVICE_KINDS. Every key is written in full, so the i18n check finds them.
 */

/** What a price buys, after the price: « 250 € l'événement ». Mirrors ServiceKind::offerUnits(), in the form's order. */
export const VISUAL_UNIT_KEYS = {
  event: 'profile.visual_service.units.event',
  hour: 'profile.visual_service.units.hour',
  day: 'profile.visual_service.units.day',
  flat: 'profile.visual_service.units.flat'
}

/** The same units as the form names them: « À l'événement ». */
export const VISUAL_FORM_UNIT_KEYS = {
  event: 'profile.visual_service.form.units.event',
  hour: 'profile.visual_service.form.units.hour',
  day: 'profile.visual_service.form.units.day',
  flat: 'profile.visual_service.form.units.flat'
}

/** A studio, and the travel to the client: neither kind works remotely (ServiceKind::worksRemotely()). */
export const VISUAL_PLACE_KEYS = {
  at_my_place: 'profile.visual_service.places.at_my_place',
  at_client_place: 'profile.visual_service.places.at_client_place'
}

/** What a work's embed takes, as both kinds' forms name it. */
export const VISUAL_WORKS_MEDIA_LABEL_KEY = 'profile.visual_service.form.works_media_url'

/** Each kind, by its ServiceKind value. */
export const VISUAL_SERVICE_KINDS = {
  photography: {
    iconColor: 'text-sky-500',
    /** « Réalisations » as a gallery: the images first, a lightbox (#1407). */
    worksLayout: 'gallery',
    /** Mirrors PhotographySpeciality, in the order the form offers them. */
    specialityKeys: {
      concert: 'profile.photographer.specialities.concert',
      promo: 'profile.photographer.specialities.promo',
      cover: 'profile.photographer.specialities.cover',
      studio: 'profile.photographer.specialities.studio',
      backstage_tour: 'profile.photographer.specialities.backstage_tour'
    },
    text: {
      plural: 'profile.photographer.plural',
      notFoundHelp: 'profile.photographer.not_found_help',
      titleDefault: 'profile.photographer.title_default',
      shareTitle: 'profile.photographer.share_title',
      pageTitle: 'profile.photographer.page_title',
      pageTitleDefault: 'profile.photographer.page_title_default',
      formTitle: 'profile.photographer.form_title',
      headlinePlaceholder: 'profile.photographer.headline_placeholder',
      deliverablePlaceholder: 'profile.photographer.deliverable_placeholder',
      worksHelp: 'profile.photographer.works_help'
    }
  },
  video: {
    iconColor: 'text-rose-500',
    /** « Réalisations » as credits, each with its video (#1407). */
    worksLayout: 'credits',
    /** Mirrors VideoSpeciality, in the order the form offers them. */
    specialityKeys: {
      clip: 'profile.videographer.specialities.clip',
      live_session: 'profile.videographer.specialities.live_session',
      concert_film: 'profile.videographer.specialities.concert_film',
      teaser: 'profile.videographer.specialities.teaser',
      editing: 'profile.videographer.specialities.editing'
    },
    text: {
      plural: 'profile.videographer.plural',
      notFoundHelp: 'profile.videographer.not_found_help',
      titleDefault: 'profile.videographer.title_default',
      shareTitle: 'profile.videographer.share_title',
      pageTitle: 'profile.videographer.page_title',
      pageTitleDefault: 'profile.videographer.page_title_default',
      formTitle: 'profile.videographer.form_title',
      headlinePlaceholder: 'profile.videographer.headline_placeholder',
      deliverablePlaceholder: 'profile.videographer.deliverable_placeholder',
      worksHelp: 'profile.videographer.works_help'
    }
  }
}
