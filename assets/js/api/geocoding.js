const PHOTON_API_URL = 'https://photon.komoot.io/api/'

export default {
  async searchCities(query, limit = 5) {
    const params = new URLSearchParams({
      q: query,
      limit: limit.toString(),
      lang: 'fr',
      'osm_tag': 'place:city'
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
