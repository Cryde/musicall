/** global: Routing */

import axios from 'axios'

// Photon answers through our own API, which caches it and keeps the visitor's address from komoot.
function toCity(city) {
  return {
    name: city.name,
    context: city.context,
    latitude: city.latitude,
    longitude: city.longitude,
    fullName: city.full_name
  }
}

export default {
  async reverseGeocode(latitude, longitude) {
    const { data } = await axios.get(
      Routing.generate('api_geocoding_reverse', { latitude, longitude })
    )
    const [city] = data.cities

    return city ? toCity(city) : null
  },

  async searchCities(query, limit = 5) {
    const { data } = await axios.get(Routing.generate('api_geocoding_cities', { q: query, limit }))

    return data.cities.map(toCity)
  }
}
