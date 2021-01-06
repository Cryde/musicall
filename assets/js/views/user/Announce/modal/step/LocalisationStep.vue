<template>
  <b-step-item :step="3" label="Localisation" icon="globe-europe">
    <h3 class="subtitle mb-5" >Quelle localisation ?</h3>

    <gmap-autocomplete @place_changed="changePlace"
                       class="input">
    </gmap-autocomplete>

    <div class="has-text-info mb-2">Indiquez de préférence une ville ou commune.</div>

    <GmapMap
        ref="map"
        class="mb-2"
        :center="map.center"
        :zoom="map.zoom"
        style="width: 100%; height: 400px"
    >
      <gmap-info-window :options="map.infoOptions" :position="map.infoWindowPos"
                        :opened="map.infoWinOpen"></gmap-info-window>

      <Gmap-Marker v-if="map.place.latitude" :label="map.place.name"
                   :position="{lat: map.place.latitude, lng: map.place.longitude}"></Gmap-Marker>
    </GmapMap>
  </b-step-item>
</template>
<script>
import {mapGetters} from "vuex";

export default {
  props: ['titles'],
  data() {
    return {
      map: {
        zoom: 13,
        center: {lat: 50.8504500, lng: 4.3487800},
        infoWinOpen: false,
        infoWindowPos: null,
        infoOptions: {
          content: '',
          pixelOffset: {
            width: 0,
            height: -35
          }
        },
        place: {
          icon: null,
          name: '',
          latitude: null,
          longitude: null,
        }
      },
    }
  },
  computed: {
    ...mapGetters('styles', ['styles']),
    ...mapGetters('announceMusician', ['selectedLocationName']),
  },
  methods: {
    changePlace(place) {
      if (!place) return

      if (place.geometry.viewport) {
        this.$refs.map.fitBounds(place.geometry.viewport);
      }

      this.map.infoWindowPos = place.geometry.location;
      this.map.infoOptions.content = `<strong>${place.formatted_address}</strong>`;

      this.map.place = {
        latitude: place.geometry.location.lat(),
        longitude: place.geometry.location.lng(),
      };

      this.$store.dispatch('announceMusician/updateLocation', {
        name: place.formatted_address,
        long: place.geometry.location.lng(),
        lat: place.geometry.location.lat()
      });

      this.map.infoWinOpen = true;
    },
    next() {
      this.$emit('next-step');
    }
  }
}
</script>