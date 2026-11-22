/**
 * A service's places (#1410, #1411), as a page sums them up and as its form edits them, kept out of the components so
 * they are tested.
 */

/** Where the work happens, for a page's header: the studio's city, how far the member travels, and remote work. */
export function placesSummary(places) {
  const list = Array.isArray(places) ? places : []
  const studio = list.find((place) => place.type === 'at_my_place')
  const travel = list.find((place) => place.type === 'at_client_place')
  return {
    studioCity: studio?.city ?? null,
    city: studio?.city ?? list.find((place) => place.city)?.city ?? null,
    travelRadius: travel ? (travel.radius ?? null) : null,
    travels: Boolean(travel),
    remote: list.some((place) => place.type === 'remote')
  }
}

const PLACE_ICONS = {
  at_my_place: 'pi pi-building',
  at_client_place: 'pi pi-car',
  remote: 'pi pi-cloud-upload'
}

/**
 * « Où » on a page: one line per place the kind names (`placeKeys`), the travel with its reach, the others with their
 * city, remote work with what the kind says of it.
 *
 * @param {object} options { placeKeys, remoteDetailKey }: each type's key, and remote work's detail
 * @param {(key: string, params?: object) => string} t vue-i18n's t
 */
export function placeLines(places, { placeKeys, remoteDetailKey = null }, t) {
  return (places ?? [])
    .filter((place) => placeKeys[place.type])
    .map((place) => ({
      icon: PLACE_ICONS[place.type],
      label: t(placeKeys[place.type]),
      detail:
        place.type === 'at_client_place' && place.radius
          ? t('profile.service_page.travels_up_to', { radius: place.radius })
          : place.type === 'remote' && remoteDetailKey
            ? t(remoteDetailKey)
            : (place.city ?? '')
    }))
}

const EMPTY_PLACE = {
  enabled: false,
  city: '',
  country: '',
  latitude: null,
  longitude: null,
  address: '',
  radius: null
}

/** The form's places from the stored ones: the studio, the travel to the client, and remote work. */
export function placesForm(places) {
  const list = Array.isArray(places) ? places : []
  const fill = (type) => {
    const place = list.find((item) => item.type === type)
    return place
      ? {
          enabled: true,
          city: place.city ?? '',
          country: place.country ?? '',
          latitude: place.latitude ?? null,
          longitude: place.longitude ?? null,
          address: place.address ?? '',
          radius: place.radius ?? null
        }
      : { ...EMPTY_PLACE }
  }
  return {
    studio: fill('at_my_place'),
    client: fill('at_client_place'),
    remote: list.some((item) => item.type === 'remote')
  }
}

/** What the API stores for the form's places: only the ticked ones, each with what its type uses. */
export function placesPayload(form) {
  const located = (place) => ({
    city: place.city || null,
    country: place.country || null,
    latitude: place.latitude ?? null,
    longitude: place.longitude ?? null
  })
  const places = []
  if (form.studio.enabled) {
    places.push({
      type: 'at_my_place',
      ...located(form.studio),
      address: form.studio.address || null
    })
  }
  if (form.client.enabled) {
    places.push({
      type: 'at_client_place',
      ...located(form.client),
      radius: form.client.radius || null
    })
  }
  if (form.remote) places.push({ type: 'remote' })
  return places
}
