<template>
  <div>
    <b-button v-if="isAuthenticated" tag="router-link" :to="{name: 'announce_musician_add'}"
              type="is-info" class="is-pulled-right">
      <i class="fas fa-bullhorn"></i> Poster une annonce
    </b-button>
    <b-tooltip v-else class="is-pulled-right" type="is-black" label="Vous devez être connecté pour poster une annonce">
      <b-button type="is-info" >
        <i class="fas fa-bullhorn"></i> Poster une annonce
      </b-button>
    </b-tooltip>

    <h1 class="subtitle is-3">Rechercher un musicien</h1>

    <div class="columns mt-lg-5">
      <div class="column is-5 is-12-mobile">
        <span class="is-block">Je recherche</span>
        <div class="columns">
          <div class="column"
               @click="changeType(option)"
               v-for="(option, i) in optionsType">
            <b-button :type="selectedTypeName === option.value ? 'is-info' : 'is-info is-light'"
                      class="is-fullwidth is-info">
              {{ option.label }}
            </b-button>
          </div>
        </div>

        <div v-if="selectedTypeName">
          <span class="is-block mt-3" v-if="selectedTypeName === 'band'">Je suis</span>
          <span class="is-block mt-3" v-else>Instrument</span>
          <v-select :options="instruments" label="musician_name" @input="changeInstrument"
                    class="has-background-white"></v-select>

          <span class="is-block mt-3">Dans le(s) style(s)</span>
          <v-select class="has-background-white" :options="styles" label="name" @input="changeStyle"
                    multiple></v-select>

          <span class="is-block mt-3">Localisation</span>
          <gmap-autocomplete @place_changed="changePlace"
                             class="input ">
          </gmap-autocomplete>

          <b-button type="is-info" class="mt-5 is-fullwidth" :disabled="!canSearch"
                    :loading="isSearching"
                    @click="send">
            <i class="fas fa-search"></i>
            Rechercher
          </b-button>
        </div>
      </div>
      <div class="column mt-3 mt-lg-0 is-7 is-12-mobile">
        <div class="has-text-right">
          <b-button type="is-info" class="is-hidden-desktop is-inline-block-mobile mb-2" @click="showMap">
            <i class="fas fa-map-marked-alt"></i>
            <span v-if="isMapVisible">Cacher la map</span>
            <span v-else>Montrer la map</span>
          </b-button>
        </div>
        <GmapMap
            ref="map"
            class="is-hidden-mobile is-block-desktop mb-2"
            :center="center"
            :zoom="zoom"
            style="width: 100%; height: 400px"
        >
          <gmap-info-window :options="infoOptions" :position="infoWindowPos"
                            :opened="infoWinOpen"></gmap-info-window>

          <Gmap-Marker v-if="this.place.latitude" :label="this.place.name"
                       :position="{lat: this.place.latitude, lng: this.place.longitude}"></Gmap-Marker>
        </GmapMap>
      </div>
    </div>

    <results/>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import vSelect from "vue-select";
import Results from './Results';
import Spinner from "../../../components/global/misc/Spinner";

export default {
  components: {Spinner, vSelect, Results},
  computed: {
    ...mapGetters('searchMusician', ['selectedTypeName', 'selectedInstrument', 'isSearching']),
    ...mapGetters('instruments', ['instruments']),
    ...mapGetters('styles', ['styles']),
    ...mapGetters('security', ['isAuthenticated']),
    canSearch() {
      return (this.selectedTypeName && this.selectedInstrument);
    },
  },
  data() {
    return {
      isMapVisible: false, // only used in responsive mode
      optionsType: [
        {label: 'un musicien', value: "musician"},
        {label: 'un groupe', value: "band"},
      ],
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
    }
  },
  mounted() {
    this.$store.dispatch('styles/loadStyles');
    this.$store.dispatch('instruments/loadInstruments');
  },
  methods: {
    showMap() {
      if (this.$refs['map'].$el.classList.contains('d-none')) {
        this.isMapVisible = true;
        this.$refs['map'].$el.classList.remove('d-none');
      } else {
        this.isMapVisible = false;
        this.$refs['map'].$el.classList.add('d-none');
      }
    },
    changeType(obj) {
      this.$store.dispatch('searchMusician/reset');
      this.$store.dispatch('searchMusician/updateSearchType', obj.value);
    },
    changeInstrument(instrument) {
      this.$store.dispatch('searchMusician/updateSelectedInstruments', instrument.id)
    },
    changeStyle(styles) {
      this.$store.dispatch('searchMusician/updateSelectedStyles', styles)
    },
    changePlace(place) {
      if (!place) return

      if (place.geometry.viewport) {
        this.$refs.map.fitBounds(place.geometry.viewport);
      }

      this.infoWindowPos = place.geometry.location;
      this.infoOptions.content = `<strong>${place.formatted_address}</strong>`;

      this.place = {
        latitude: place.geometry.location.lat(),
        longitude: place.geometry.location.lng(),
      };

      this.$store.dispatch('searchMusician/updateLocation', {
        long: place.geometry.location.lng(),
        lat: place.geometry.location.lat()
      });

      this.infoWinOpen = true;
    },
    send() {
      this.$store.dispatch('searchMusician/search');
    }
  },
  destroyed() {
    this.$store.dispatch('searchMusician/reset');
  }
}
</script>

<style>
.vue-map {
  height: 100%
}

.selected .btn-selection-type {
  background: #97C2E8;
  color: white;
}

.btn-selection-type {
  padding: 5px 10px;
  text-align: center;
  display: inline-block;
  border: 1px solid #ccc;
  background: white;
  cursor: pointer;
  transition: all 200ms;
  border-radius: 0.25rem;
}
</style>