<template>
    <b-row>
        <b-col cols="12">
            <h1>Ajouter une annonce musicien</h1>

            <b-row>
                <b-col xl="8" offset-xl="2" class="mt-5 text-center">
                    <h2 class="mb-3">Que rechercher vous ? </h2>
                    <div class="selectable-button pl-5 pr-5 pt-3 pb-3 d-lg-inline-block d-block mr-lg-3 mb-2 mb-lg-0"
                         :class="{'selected': search === 'musician'}"
                         @click="selectSearch('musician')">
                        Je recherche un musicien
                    </div>
                    <div class="selectable-button pl-5 pr-5 pt-3 pb-3 d-lg-inline-block d-block"
                         :class="{'selected': search === 'band'}" @click="selectSearch('band')">
                        Je recherche un groupe
                    </div>
                </b-col>
            </b-row>

            <fade-transition :duration="100" origin="center top" mode="out-in">
                <b-row v-if="search !== ''">
                    <b-col xl="8" offset-xl="2" class="mt-5 text-center">
                        <h2 class="mb-3">{{ titles[search].instrument }}</h2>

                        <span v-for="instrument in instruments"
                              class="selectable-button d-inline-block mb-1 pl-4 pr-4 pt-2 pb-2 mr-1"
                              :class="{'selected': selectedInstrument.id === instrument.id}"
                              @click="selectInstrument(instrument)"
                        >
                            {{ instrument.name }}
                        </span>
                    </b-col>

                    <b-col xl="8" offset-xl="2" class="mt-5 text-center">
                        <h2 class="mb-3">{{ titles[search].styles }}</h2>

                        <span v-for="style in firstStyles"
                              class="selectable-button d-inline-block pl-4 mb-1 pr-4 pt-2 pb-2 mr-1"
                              :class="{'selected': selectedStyles.includes(style)}"
                              @click="addSelectedStyle(style)"
                        >
                            {{ style.name }}
                        </span>

                        <div></div>
                        <b-button v-if="!seeMoreStyle" size="sm" variant="primary" class="mt-2 mb-2"
                                  @click="seeMoreStyle = true">
                            Voir plus de styles
                        </b-button>
                        <b-button v-else variant="primary" size="sm" class="mt-2 mb-2" @click="seeMoreStyle = false">
                            Voir moins de styles
                        </b-button>
                        <div></div>
                        <span v-for="style in restStyles"
                              v-if="seeMoreStyle"
                              class="selectable-button d-inline-block pl-4 mb-1 pr-4 pt-2 pb-2 mr-1"
                              :class="{'selected': selectedStyles.includes(style)}"
                              @click="addSelectedStyle(style)"
                        >
                            {{ style.name }}
                        </span>
                    </b-col>


                    <b-col xl="8" offset-xl="2" class="mt-5 text-center">
                        <h2 class="mb-3">{{ titles[search].localisation }}</h2>
                        <input type="text" class="form-control" ref="search"/>
                        <div class="text-left text-info">Indiquez de préférence une ville ou commune.</div>
                        <div id="map" ref="map" style="height: 400px" class="mt-3"></div>
                    </b-col>

                    <b-col xl="8" offset-xl="2" class="mt-5 text-center" v-if="isValid">
                        <h2 class="mb-3">Récapitulatif</h2>
                        <div class="preview" v-if="search === 'band'">
                            Je suis un-e <strong>{{ selectedInstrument.musician_name.toLocaleLowerCase() }}</strong> et
                            je recherche un groupe jouant du
                            <strong>{{ selectedStyles.map(style => style.name.toLocaleLowerCase()).join(', ')
                                }}</strong> dans les alentours de <strong>{{ selectedLocationName }}</strong>.
                        </div>
                        <div class="preview" v-else>
                            Je recherche un-e <strong>{{ selectedInstrument.musician_name.toLocaleLowerCase()
                            }}</strong> jouant du
                            <strong>{{ selectedStyles.map(style => style.name.toLocaleLowerCase()).join(', ')
                                }}</strong> dans les alentours de <strong>{{ selectedLocationName }}</strong>.
                        </div>
                    </b-col>

                    <b-col xl="8" offset-xl="2" class="mt-5 text-center">
                        <b-button variant="primary" size="lg" :disabled="!isValid">Créer mon annonce</b-button>
                    </b-col>
                </b-row>
            </fade-transition>
        </b-col>
    </b-row>
</template>

<script>
  import {mapGetters} from 'vuex';
  import {FadeTransition} from 'vue2-transitions';
  import {Loader} from 'google-maps';

  export default {
    components: {FadeTransition},
    data() {
      return {
        search: '',
        band: {},
        musician: {},
        map: null,
        seeMoreStyle: false,
      }
    },
    computed: {
      ...mapGetters('instruments', ['instruments']),
      ...mapGetters('styles', ['styles']),
      ...mapGetters('announceMusician', ['selectedInstrument', 'selectedStyles', 'selectedAnnounceTypeName', 'selectedLocationName']),
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
    mounted() {
      this.$store.dispatch('styles/loadStyles');
      this.$store.dispatch('instruments/loadInstruments');
    },
    methods: {
      selectSearch(flow) {
        this.search = flow;
        this.$store.dispatch('announceMusician/updateAnnounceType', flow);
        !this.map && this.initGoogle();
      },
      selectInstrument(instrument) {
        this.$store.dispatch('announceMusician/updateSelectedInstruments', {instrument});
      },
      addSelectedStyle(style) {
        this.$store.dispatch('announceMusician/updateSelectedStyles', {style});
      },
      async initGoogle() {
        const loader = new Loader('AIzaSyBsfoARa2MWlsB-1lUxwHjk6Z_4Xwcp-mQ', {
          libraries: ['places'],
          language: 'fr',
        });

        const google = await loader.load();
        await this.initMap(google);
        await this.initAutoComplete(google);
      },
      async initMap(google) {
        this.map = new google.maps.Map(this.$refs['map'], {
          center: {lat: 50.8504500, lng: 4.3487800},
          zoom: 13
        });
      },
      async initAutoComplete(google) {
        const autocomplete = new google.maps.places.Autocomplete(this.$refs['search']);
        autocomplete.bindTo('bounds', this.map);

        const infoWindow = new google.maps.InfoWindow();

        const marker = new google.maps.Marker({
          map: this.map,
          anchorPoint: new google.maps.Point(0, -29)
        });

        autocomplete.addListener('place_changed', () => {

          infoWindow.close();
          marker.setVisible(false);

          // Get the place details from the autocomplete object.
          const place = autocomplete.getPlace();
          if (!place.geometry) {
            // User entered the name of a Place that was not suggested and
            // pressed the Enter key, or the Place Details request failed.
            window.alert("Il n'existe pas un lieu connu pour : '" + place.name + "'");
            return;
          }


          // If the place has a geometry, then present it on a map.
          if (place.geometry.viewport) {
            this.map.fitBounds(place.geometry.viewport);
          } else {
            this.map.setCenter(place.geometry.location);
            this.map.setZoom(17);  // Why 17? Because it looks good.
          }

          marker.setIcon(/** @type {google.maps.Icon} */({
            url: place.icon,
            size: new google.maps.Size(71, 71),
            origin: new google.maps.Point(0, 0),
            anchor: new google.maps.Point(17, 34),
            scaledSize: new google.maps.Size(35, 35)
          }));
          marker.setPosition(place.geometry.location);
          marker.setVisible(true);

          let address = '';
          if (place.address_components) {
            address = [
              (place.address_components[0] && place.address_components[0].short_name || ''),
              (place.address_components[1] && place.address_components[1].short_name || ''),
              (place.address_components[2] && place.address_components[2].short_name || '')
            ].join(' ');
          }

          infoWindow.setContent('<div><strong>' + place.name + '</strong><br>' + address);
          infoWindow.open(this.map, marker);

          const long = place.geometry.location.lng();
          const lat = place.geometry.location.lat();
          this.$store.dispatch('announceMusician/updateLocation', {lat, long, name: place.formatted_address});
        });
      }
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
</style>