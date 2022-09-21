<template>
  <div>

    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'search_index'}, label: 'Recherche'}"
        :current="{label: 'Rechercher un musicien'}"
    />

    <b-button
        @click="openAddMusicianAnnounce()"
        icon-left="bullhorn"
        type="is-info"
        v-if="isAuthenticated"
        class="is-pulled-right">
      Poster une annonce
    </b-button>
    <b-tooltip v-else class="is-pulled-right" type="is-black" label="Vous devez être connecté pour poster une annonce">
      <b-button type="is-info" >
        <i class="fas fa-bullhorn"></i> Poster une annonce
      </b-button>
    </b-tooltip>

    <h1 class="subtitle is-3">Rechercher un musicien ou un groupe</h1>

    <div class="columns mt-lg-5">
      <div class="column is-5 is-12-mobile">
        <span class="is-block">Je recherche</span>
        <div class="columns">
          <div class="column mt-1"
               @click="changeType(option)"
               v-for="(option, i) in optionsType">
            <div
                :class="selectedTypeName === option.value ? 'has-background-info has-text-white' : 'has-background-light has-text-info'"
                      class="is-fullwidth p-2 has-text-centered box">
              {{ option.label }}

              <figure class="image is-48x48 container mb-2 mt-2">
                <img :src="option.path"/>
              </figure>
            </div>
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
          <gmap-autocomplete  @place_changed="changePlace">
            <template v-slot:default="slotProps">
             <b-input
                      placeholder="Indiquez un lieu"
                      ref="input"
                      v-on:listeners="slotProps.listeners"
                      v-on:attrs="slotProps.attrs"
             />
            </template>
          </gmap-autocomplete>

          <b-button type="is-info" class="mt-5 is-fullwidth" :disabled="!canSearch" icon-left="search"
                    :loading="isSearching" label="Rechercher"
                    @click="send" />
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
import Breadcrumb from "../../../components/global/Breadcrumb";
import {TYPES_ANNOUNCE_BAND_LABEL, TYPES_ANNOUNCE_MUSICIAN_LABEL} from "../../../constants/types";
import AddMusicianAnnounceForm from "../../user/Announce/modal/AddMusicianAnnounceForm";
import musicianPath from '../../../../images/announce/musician/musician.png'
import bandPath from '../../../../images/announce/musician/band.png'

export default {
  components: {Breadcrumb, Spinner, vSelect, Results},
  metaInfo() {
    return {
      title: 'Recherche un musicien ou un groupe - MusicAll',
    }
  },
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
        {label: 'un musicien', value: TYPES_ANNOUNCE_MUSICIAN_LABEL, path: musicianPath},
        {label: 'un groupe', value: TYPES_ANNOUNCE_BAND_LABEL, path: bandPath},
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
    },
    openAddMusicianAnnounce() {
      this.$buefy.modal.open({
        parent: this,
        component: AddMusicianAnnounceForm,
        hasModalCard: true,
        trapFocus: true
      })
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