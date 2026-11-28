import { PRODUCTION_SPECIALITY_KEYS } from './production.js'
import { SPECIALITY_KEYS, UNIT_KEYS } from './soundEngineering.js'
import { VISUAL_SERVICE_KINDS, VISUAL_UNIT_KEYS } from './visualServices.js'

/**
 * The searches by speciality (#1410, #1411, #1412, #1413), one page for all of them, by ServiceKind value: what each
 * one asks the API, offers as filters and says. The words every one of them shares are under `search.service`. Each
 * search's intro and count are on SERVICE_KINDS, which the annuaire's cards read too (#1414).
 */
export const SPECIALITY_SEARCHES = {
  sound_engineering: {
    apiRoute: 'api_services_sound_engineering_search',
    specialityKeys: SPECIALITY_KEYS,
    unitKeys: UNIT_KEYS,
    /** Remote work widens a search around a city. */
    hasOnline: true,
    /** The days a card names, « 5 jours de délai ». */
    daysField: 'turnaround_days',
    text: {
      heading: 'search.sound_engineer.heading',
      cta: 'search.sound_engineer.engineer_cta',
      online: 'search.service.online',
      loadMore: 'search.sound_engineer.load_more',
      emptyTitle: 'search.sound_engineer.empty_title',
      emptyText: 'search.service.empty_text_online',
      title: 'search.sound_engineer.title'
    }
  },
  photography: {
    apiRoute: 'api_services_photography_search',
    specialityKeys: VISUAL_SERVICE_KINDS.photography.specialityKeys,
    unitKeys: VISUAL_UNIT_KEYS,
    hasOnline: VISUAL_SERVICE_KINDS.photography.worksRemotely,
    daysField: 'delivery_days',
    text: {
      heading: 'search.photographer.heading',
      cta: 'search.photographer.cta',
      loadMore: 'search.photographer.load_more',
      emptyTitle: 'search.photographer.empty_title',
      emptyText: 'search.service.empty_text',
      title: 'search.photographer.title'
    }
  },
  video: {
    apiRoute: 'api_services_video_search',
    specialityKeys: VISUAL_SERVICE_KINDS.video.specialityKeys,
    unitKeys: VISUAL_UNIT_KEYS,
    hasOnline: VISUAL_SERVICE_KINDS.video.worksRemotely,
    daysField: 'delivery_days',
    text: {
      heading: 'search.videographer.heading',
      cta: 'search.videographer.cta',
      loadMore: 'search.videographer.load_more',
      emptyTitle: 'search.videographer.empty_title',
      emptyText: 'search.service.empty_text',
      title: 'search.videographer.title'
    }
  },
  graphic_design: {
    apiRoute: 'api_services_graphic_design_search',
    specialityKeys: VISUAL_SERVICE_KINDS.graphic_design.specialityKeys,
    unitKeys: VISUAL_UNIT_KEYS,
    hasOnline: VISUAL_SERVICE_KINDS.graphic_design.worksRemotely,
    daysField: 'delivery_days',
    text: {
      heading: 'search.graphic_designer.heading',
      cta: 'search.graphic_designer.cta',
      online: 'search.service.online',
      loadMore: 'search.graphic_designer.load_more',
      emptyTitle: 'search.graphic_designer.empty_title',
      emptyText: 'search.service.empty_text_online',
      title: 'search.graphic_designer.title'
    }
  },
  web: {
    apiRoute: 'api_services_web_search',
    specialityKeys: VISUAL_SERVICE_KINDS.web.specialityKeys,
    unitKeys: VISUAL_UNIT_KEYS,
    hasOnline: VISUAL_SERVICE_KINDS.web.worksRemotely,
    daysField: 'delivery_days',
    text: {
      heading: 'search.webmaster.heading',
      cta: 'search.webmaster.cta',
      online: 'search.service.online',
      loadMore: 'search.webmaster.load_more',
      emptyTitle: 'search.webmaster.empty_title',
      emptyText: 'search.service.empty_text_online',
      title: 'search.webmaster.title'
    }
  },
  production: {
    apiRoute: 'api_services_production_search',
    specialityKeys: PRODUCTION_SPECIALITY_KEYS,
    unitKeys: UNIT_KEYS,
    hasOnline: true,
    /** No delay on a producer's card. */
    daysField: null,
    text: {
      heading: 'search.producer.heading',
      cta: 'search.producer.cta',
      online: 'search.service.online',
      loadMore: 'search.producer.load_more',
      emptyTitle: 'search.producer.empty_title',
      emptyText: 'search.service.empty_text_online',
      title: 'search.producer.title'
    }
  }
}
