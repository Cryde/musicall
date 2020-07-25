<template>
    <div>
        <b-button v-if="isAuthenticated" :to="{name: 'announce_musician_add'}"
                  variant="primary" class="float-right">
            <i class="fas fa-bullhorn"></i> Poster une annonce
        </b-button>
        <b-button v-else variant="primary" class="float-right" v-b-tooltip.hover
                  title="Vous devez être connecté pour poster une annonce">
            <i class="fas fa-bullhorn"></i> Poster une annonce
        </b-button>

        <h1>Rechercher un musicien</h1>

        <b-row class="mt-lg-5">
            <b-col cols="12" xl="5" order-xl="1" order="2" class="mt-2 mt-lg-0">
                <span class="d-block">Je recherche</span>
                <v-select :options="optionsType"
                          class="bg-white"
                          @input="changeType"></v-select>

                <div v-if="selectedTypeName">
                    <span class="d-block mt-3" v-if="selectedTypeName === 'band'">Je suis</span>
                    <span class="d-block mt-3" v-else>Instrument</span>
                    <v-select :options="instruments" label="musician_name" @input="changeInstrument"
                              class="bg-white"></v-select>

                    <span class="d-block mt-3">Dans le(s) style(s)</span>
                    <v-select class="bg-white" :options="styles" label="name" @input="changeStyle" multiple></v-select>

                    <span class="d-block mt-3">Localisation</span>
                    <gmap-autocomplete @place_changed="changePlace"
                                       class="form-control">
                    </gmap-autocomplete>

                    <b-button block variant="primary" class="mt-5" :disabled="!canSearch"
                              @click="send">
                        <b-spinner v-if="isSearching" small/>
                        <i v-else class="fas fa-search"></i>
                        Rechercher
                    </b-button>
                </div>
            </b-col>
            <b-col cols="12" xl="7" order-xl="2" order="1" class="mt-3 mt-lg-0">
                <div class="text-right">
                    <b-button variant="primary" class="d-inline-block d-lg-none mb-2" @click="showMap">
                        <i class="fas fa-map-marked-alt"></i>
                        <span v-if="isMapVisible">Cacher la map</span>
                        <span v-else>Montrer la map</span>
                    </b-button>
                </div>
                <GmapMap
                        ref="map"
                        class="d-none d-lg-block mb-2"
                        :center="center"
                        :zoom="zoom"
                        style="width: 100%; height: 400px"
                >
                    <gmap-info-window :options="infoOptions" :position="infoWindowPos"
                                      :opened="infoWinOpen"></gmap-info-window>

                    <Gmap-Marker v-if="this.place.latitude" :label="this.place.name"
                                 :position="{lat: this.place.latitude, lng: this.place.longitude}"></Gmap-Marker>
                </GmapMap>
            </b-col>
        </b-row>

        <b-row>
            <results/>
        </b-row>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import {gmapApi} from 'gmap-vue'
  import vSelect from "vue-select";
  import Results from './Results';

  export default {
    components: {vSelect, Results},
    computed: {
      ...mapGetters('searchMusician', ['selectedTypeName', 'selectedInstrument', 'isSearching']),
      ...mapGetters('instruments', ['instruments']),
      ...mapGetters('styles', ['styles']),
      ...mapGetters('security', ['isAuthenticated']),
      google: gmapApi,
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
    }
  }
</script>

<style>
    .vue-map {
        height: 100%
    }
</style>