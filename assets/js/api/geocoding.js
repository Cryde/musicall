const PHOTON_API_URL = 'https://photon.komoot.io/api/'
const PHOTON_REVERSE_URL = 'https://photon.komoot.io/reverse'

export default {
  async reverseGeocode(latitude, longitude) {
    // Don't use osm_tag filter for reverse geocoding - it's too restrictive
    // Photon returns the nearest feature, we'll extract city from properties
    const params = new URLSearchParams({
      lat: latitude.toString(),
      lon: longitude.toString(),
      lang: 'fr'
    })

    const response = await fetch(`${PHOTON_REVERSE_URL}?${params}`)
    const data = await response.json()

    if (!data.features || data.features.length === 0) {
      return null
    }

    const feature = data.features[0]
    const props = feature.properties

    // For reverse geocoding, prioritize the city property (most useful for our use case)
    // Fall back to name, county, etc. if city is not available
    const locationName = props.city || props.name || props.county || props.state

    if (!locationName) {
      return null
    }

    // Build context, filtering out duplicates and the location name itself
    const contextParts = [...new Set([props.county, props.state, props.country].filter(Boolean))]
      .filter(part => part !== locationName)

    return {
      name: locationName,
      context: contextParts.join(', '),
      latitude: latitude,
      longitude: longitude,
      fullName: [locationName, ...contextParts].filter(Boolean).join(', ')
    }
  },

  async searchCities(query, limit = 5) {
    const params = new URLSearchParams({
      q: query,
      limit: limit.toString(),
      lang: 'fr',
      osm_tag: 'place:city'
    })

    // Photon doesn't support multiple osm_tag in URLSearchParams, need to append manually
    const url = `${PHOTON_API_URL}?${params}&osm_tag=place:town&osm_tag=place:village&osm_tag=place:municipality`

    const response = await fetch(url)
    const data = await response.json()

    return data.features.map((feature) => {
      const props = feature.properties
      const coords = feature.geometry.coordinates

      // Build context, filtering out duplicates
      const contextParts = [...new Set([props.county, props.state, props.country].filter(Boolean))]

      return {
        name: props.name,
        context: contextParts.join(', '),
        latitude: coords[1],
        longitude: coords[0],
        fullName: [props.name, ...contextParts].join(', ')
      }
    })
  }
}
