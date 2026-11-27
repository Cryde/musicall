/**
 * The kinds shown by their works: photographers and videographers (#1411), graphic designers and webmasters (#1412). One
 * page and one form for all of them, each kind its own words, units and works layout; their icon, page and search are
 * in SERVICE_KINDS. Every key is written in full, so the i18n check finds them.
 */

/**
 * What a price buys, after the price: « 250 € l'événement ». Every unit these kinds price in; each kind's `offerUnits`
 * picks its own.
 */
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

/** A studio, the travel to the client, and remote work for a kind given that way (`worksRemotely`). */
export const VISUAL_PLACE_KEYS = {
  at_my_place: 'profile.visual_service.places.at_my_place',
  at_client_place: 'profile.visual_service.places.at_client_place',
  remote: 'profile.visual_service.places.remote'
}

/** What a work's embed takes, as every kind's form names it. */
export const VISUAL_WORKS_MEDIA_LABEL_KEY = 'profile.visual_service.form.works_media_url'

/** Mirrors ServiceKind::offerUnits() of a photographer and a videographer, in the form's order. */
const EVENT_UNITS = ['event', 'hour', 'day', 'flat']
/** Mirrors ServiceKind::offerUnits() of a graphic designer and a webmaster: a project, not an event (#1412). */
const PROJECT_UNITS = ['flat', 'hour', 'day']

/** Each kind, by its ServiceKind value. `worksRemotely` mirrors ServiceKind::worksRemotely(). */
export const VISUAL_SERVICE_KINDS = {
  photography: {
    iconColor: 'text-sky-500',
    /** « Réalisations » as a gallery: the images first, a lightbox (#1407). */
    worksLayout: 'gallery',
    offerUnits: EVENT_UNITS,
    worksRemotely: false,
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
    offerUnits: EVENT_UNITS,
    worksRemotely: false,
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
  },
  graphic_design: {
    iconColor: 'text-emerald-500',
    /** « Réalisations » as a gallery, as a photographer's (#1412). */
    worksLayout: 'gallery',
    offerUnits: PROJECT_UNITS,
    worksRemotely: true,
    /** Mirrors GraphicDesignSpeciality, in the order the form offers them. */
    specialityKeys: {
      cover: 'profile.graphic_designer.specialities.cover',
      logo: 'profile.graphic_designer.specialities.logo',
      poster: 'profile.graphic_designer.specialities.poster',
      merch: 'profile.graphic_designer.specialities.merch',
      visual_identity: 'profile.graphic_designer.specialities.visual_identity',
      social_media: 'profile.graphic_designer.specialities.social_media'
    },
    text: {
      plural: 'profile.graphic_designer.plural',
      notFoundHelp: 'profile.graphic_designer.not_found_help',
      titleDefault: 'profile.graphic_designer.title_default',
      shareTitle: 'profile.graphic_designer.share_title',
      pageTitle: 'profile.graphic_designer.page_title',
      pageTitleDefault: 'profile.graphic_designer.page_title_default',
      formTitle: 'profile.graphic_designer.form_title',
      headlinePlaceholder: 'profile.graphic_designer.headline_placeholder',
      deliverablePlaceholder: 'profile.graphic_designer.deliverable_placeholder',
      worksHelp: 'profile.graphic_designer.works_help'
    }
  },
  web: {
    iconColor: 'text-indigo-500',
    /** « Réalisations » as sites: each one's address and the screenshots its member uploaded (#1412). */
    worksLayout: 'sites',
    offerUnits: PROJECT_UNITS,
    worksRemotely: true,
    /** Mirrors WebSpeciality, in the order the form offers them. */
    specialityKeys: {
      band_site: 'profile.webmaster.specialities.band_site',
      epk: 'profile.webmaster.specialities.epk',
      online_store: 'profile.webmaster.specialities.online_store',
      redesign_maintenance: 'profile.webmaster.specialities.redesign_maintenance'
    },
    text: {
      plural: 'profile.webmaster.plural',
      notFoundHelp: 'profile.webmaster.not_found_help',
      titleDefault: 'profile.webmaster.title_default',
      shareTitle: 'profile.webmaster.share_title',
      pageTitle: 'profile.webmaster.page_title',
      pageTitleDefault: 'profile.webmaster.page_title_default',
      formTitle: 'profile.webmaster.form_title',
      headlinePlaceholder: 'profile.webmaster.headline_placeholder',
      deliverablePlaceholder: 'profile.webmaster.deliverable_placeholder',
      worksHelp: 'profile.webmaster.works_help',
      /** A site's address, the link its work leads to. */
      worksLink: 'profile.webmaster.works_link'
    }
  }
}
