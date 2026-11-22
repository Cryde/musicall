import { SPECIALITY_KEYS, UNIT_KEYS } from './soundEngineering.js'
import { VISUAL_SERVICE_KINDS, VISUAL_UNIT_KEYS } from './visualServices.js'

/**
 * The searches by speciality (#1410, #1411), one page for all three, by ServiceKind value: what each one asks the API,
 * offers as filters and says. The words every one of them shares are under `search.service`.
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
      intro: 'search.sound_engineer.intro',
      cta: 'search.sound_engineer.engineer_cta',
      online: 'search.sound_engineer.online',
      count: 'search.sound_engineer.count',
      loadMore: 'search.sound_engineer.load_more',
      emptyTitle: 'search.sound_engineer.empty_title',
      emptyText: 'search.sound_engineer.empty_text',
      title: 'search.sound_engineer.title'
    }
  },
  photography: {
    apiRoute: 'api_services_photography_search',
    specialityKeys: VISUAL_SERVICE_KINDS.photography.specialityKeys,
    unitKeys: VISUAL_UNIT_KEYS,
    hasOnline: false,
    daysField: 'delivery_days',
    text: {
      heading: 'search.photographer.heading',
      intro: 'search.photographer.intro',
      cta: 'search.photographer.cta',
      count: 'search.photographer.count',
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
    hasOnline: false,
    daysField: 'delivery_days',
    text: {
      heading: 'search.videographer.heading',
      intro: 'search.videographer.intro',
      cta: 'search.videographer.cta',
      count: 'search.videographer.count',
      loadMore: 'search.videographer.load_more',
      emptyTitle: 'search.videographer.empty_title',
      emptyText: 'search.service.empty_text',
      title: 'search.videographer.title'
    }
  }
}
