<template>
  <div class="columns">
    <div class="column" v-if="isSuccess">
      <h1 class="subtitle is-3">Ajouter une annonce musicien</h1>

      <div class="has-text-centered p-5">
        <i class="fas fa-check fa-5x has-text-success  mb-3"></i><br/>
        Votre annonce est créée.<br/> Vous pouvez
        <router-link :to="{name: 'user_musician_announce'}">retrouver vos annonces ici</router-link>
      </div>
    </div>
    <div class="column" v-else>
      <h1 class="subtitle is-3">Ajouter une annonce musicien</h1>

      <div class="columns">
        <div class="column is-8 is-offset-2 mt-5 has-text-centered">
          <h2 class="mb-3 subtitle is-5">Que rechercher vous ? </h2>
          <div
              class="selectable-button pl-5 pr-5 pt-3 pb-3 is-inline-block-desktop is-block-mobile mr-lg-3 mb-2 mb-lg-0"
              :class="{'selected': search === 'musician'}"
              @click="selectSearch('musician')">
            Je recherche un musicien
          </div>
          <div class="selectable-button pl-5 pr-5 pt-3 pb-3 is-inline-block-desktop is-block-mobile"
               :class="{'selected': search === 'band'}" @click="selectSearch('band')">
            Je recherche un groupe
          </div>
        </div>
      </div>

      <div v-if="search !== ''">
        <div class="columns">
          <div class="column is-8 is-offset-2 mt-5 has-text-centered">
            <h2 class="mb-3 subtitle is-5">{{ titles[search].instrument }}</h2>

            <span v-for="instrument in instruments"
                  class="selectable-button is-inline-block mb-1 pl-4 pr-4 pt-2 pb-2 mr-1"
                  :class="{'selected': selectedInstrument.id === instrument.id}"
                  @click="selectInstrument(instrument)"
            >
                            {{ instrument.name }}
                        </span>
          </div>
        </div>
        <div class="columns">
          <div class="column is-8 is-offset-2 mt-5 has-text-centered">
            <h2 class="mb-3 subtitle is-5">{{ titles[search].styles }}</h2>

            <span v-for="style in firstStyles"
                  class="selectable-button is-inline-block pl-4 mb-1 pr-4 pt-2 pb-2 mr-1"
                  :class="{'selected': selectedStyles.includes(style)}"
                  @click="addSelectedStyle(style)"
            >
                            {{ style.name }}
                        </span>

            <div></div>
            <b-button v-if="!seeMoreStyle" size="sm" type="is-default" class="mt-2 mb-2"
                      @click="seeMoreStyle = true">
              Voir plus de styles
            </b-button>
            <b-button v-else variant="primary" size="sm" class="mt-2 mb-2" @click="seeMoreStyle = false">
              Voir moins de styles
            </b-button>
            <div></div>
            <span v-for="style in restStyles"
                  v-if="seeMoreStyle"
                  class="selectable-button is-inline-block pl-4 mb-1 pr-4 pt-2 pb-2 mr-1"
                  :class="{'selected': selectedStyles.includes(style)}"
                  @click="addSelectedStyle(style)"
            >
                    {{ style.name }}
                </span>
          </div>
        </div>
        <div class="columns">

          <div class="column is-8 is-offset-2 mt-5 has-text-centered">
            <h2 class="mb-3 subtitle is-5">{{ titles[search].localisation }}</h2>
            <gmap-autocomplete @place_changed="changePlace"
                               :disabled="isSending"
                               class="input">
            </gmap-autocomplete>


            <div class="text-left text-info">Indiquez de préférence une ville ou commune.</div>

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
          </div>
        </div>
        <div class="columns">
          <div class="column is-8 is-offset-2 mt-5 has-text-centered">
            <h2 class="mb-3 subtitle is-5">Détails supplémentaire</h2>

            <b-input
                type="textarea"
                :disabled="isSending"
                v-model="note"
                @keyup.native="updateNote"
                rows="3" max-rows="5"
                placeholder="Ajoutez des détails ici. Ex : groupe favoris, lien vers un morceau, ..."></b-input>
          </div>
        </div>

        <div class="columns">
          <div class="column is-8 is-offset-2 mt-5 has-text-centered" v-if="isValid">
            <h2 class="mb-3 subtitle is-5">Récapitulatif</h2>
            <div class="preview" v-if="search === 'band'">
              Je suis un-e <strong>{{ selectedInstrument.musician_name.toLocaleLowerCase() }}</strong> et
              je recherche un groupe jouant du
              <strong>{{
                  selectedStyles.map(style => style.name.toLocaleLowerCase()).join(', ')
                }}</strong> dans les alentours de <strong>{{ selectedLocationName }}</strong>.
            </div>
            <div class="preview" v-else>
              Je recherche un-e <strong>{{
                selectedInstrument.musician_name.toLocaleLowerCase()
              }}</strong> jouant du
              <strong>{{
                  selectedStyles.map(style => style.name.toLocaleLowerCase()).join(', ')
                }}</strong> dans les alentours de <strong>{{ selectedLocationName }}</strong>.
            </div>
          </div>
        </div>
        <div class="columns">
          <div class="column is-8 is-offset-2 mt-5 has-text-centered">
            <b-button type="is-info"
                      :loading="isSending"
                      size="is-large" :disabled="!isValid || isSending" @click="send">
              Créer mon annonce
            </b-button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';

export default {
  data() {
    return {
      search: '',
      note: '',
      band: {},
      musician: {},
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
      seeMoreStyle: false,
    }
  },
  computed: {
    ...mapGetters('instruments', ['instruments']),
    ...mapGetters('styles', ['styles']),
    ...mapGetters('announceMusician', ['isSuccess', 'isSending', 'selectedInstrument', 'selectedStyles', 'selectedAnnounceTypeName', 'selectedLocationName']),
    firstStyles() {
      return [...this.styles].slice(0, 5);
    },
    restStyles() {
      return [...this.styles].slice(5);
    },
    titles() {
      return {
        musician: {
          instrument: 'Quel instrument cherchez vous ?',
          styles: 'Quels styles cherchez vous ?',
          localisation: 'Quelle localisation ?',
        },
        band: {
          instrument: 'Quel instrument jouez vous ?',
          styles: 'Quels styles jouez vous ?',
          localisation: 'Quelle localisation ?',
        }
      };
    },
    isValid() {
      return this.selectedInstrument !== '' && this.selectedStyles.length > 0 && this.selectedAnnounceTypeName !== '' && this.selectedLocationName !== '';
    }
  },
  async mounted() {
    await this.$store.dispatch('announceMusician/reset');
    this.reset();
    this.$store.dispatch('styles/loadStyles');
    this.$store.dispatch('instruments/loadInstruments');
    this.selectSearch('musician');
  },
  methods: {
    reset() {
      this.search = '';
      this.note = '';
      this.band = {};
      this.musician = {};
      this.map = {
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
      };
      this.seeMoreStyle = false;
    },
    selectSearch(flow) {
      if (this.isSending) {
        return;
      }
      this.search = flow;
      this.$store.dispatch('announceMusician/updateAnnounceType', flow);
    },
    selectInstrument(instrument) {
      if (this.isSending) {
        return;
      }
      this.$store.dispatch('announceMusician/updateSelectedInstruments', {instrument});
    },
    addSelectedStyle(style) {
      if (this.isSending) {
        return;
      }
      this.$store.dispatch('announceMusician/updateSelectedStyles', {style});
    },
    updateNote(elem) {
      this.$store.dispatch('announceMusician/updateNote', elem.target.value);
    },
    async send() {
      await this.$store.dispatch('announceMusician/send');
    },
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

      console.log({
        name: place.formatted_address,
        long: place.geometry.location.lng(),
        lat: place.geometry.location.lat()
      })

      this.$store.dispatch('announceMusician/updateLocation', {
        name: place.formatted_address,
        long: place.geometry.location.lng(),
        lat: place.geometry.location.lat()
      });

      this.map.infoWinOpen = true;
    },
  }
}
</script>

<style>
.selectable-button {
  border: 1px solid #ccc;
  background: white;
  cursor: pointer;
  transition: all 200ms;
  border-radius: 0.25rem;
}

.selectable-button.selected {
  background: #97C2E8;
  color: white;
}

.vue-map {
  height: 100%
}
</style>