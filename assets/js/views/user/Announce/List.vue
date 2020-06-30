<template>
    <div v-if="isLoading" class="text-center pb-3 pt-3">
        <b-spinner/>
    </div>
    <div v-else>
        <b-button :to="{name: 'announce_musician_add'}"
                  variant="primary" class="float-right">
            <i class="fas fa-bullhorn"></i> Ajouter une nouvelle annonce
        </b-button>
        <h1>Mes annonces</h1>

        <b-table class="mt-5" striped hover :fields="fields" :items="announces" :busy="isLoading" show-empty>
            <template v-slot:table-busy>
                <div class="text-center text-danger my-2">
                    <b-spinner class="align-middle"></b-spinner>
                </div>
            </template>

            <template v-slot:empty="scope">
                <div class="p-3">Vous n'avez pas encore d'annonces.</div>
            </template>

            <template v-slot:cell(type)="data">
                {{ data.item.type | getSearchTypeName }}
            </template>

            <template v-slot:cell(instrument)="data">
                {{ data.item.instrument.musician_name }}
            </template>

            <template v-slot:cell(styles)="data">
                {{ data.item.styles.map(item => item.name).join(', ') }}
            </template>

            <template v-slot:cell(creation_datetime)="data">
                {{ data.item.creation_datetime | prettyDate }}
            </template>

            <template v-slot:cell(location_name)="data">
                {{ data.item.location_name }}
            </template>

            <template v-slot:cell(note)="data">
                {{ data.item.note }}
            </template>

            <template v-slot:cell(actions)="data">

            </template>
        </b-table>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import {TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN} from "../../../constants/types";

  export default {
    data() {
      return {
        fields: [
          {key: 'type', label: 'Recherche'},
          {key: 'instrument', label: 'Instrument'},
          {key: 'styles', label: 'Styles'},
          {key: 'creation_datetime', label: 'Date de création'},
          {key: 'note', label: 'Note'},
          {key: 'location_name', label: 'Localisation'},
          {key: 'actions', label: ''},
        ]
      }
    },
    computed: {
      ...mapGetters('userMusicianAnnounces', ['isLoading', 'announces']),
    },
    mounted() {
      this.$store.dispatch('userMusicianAnnounces/load');
    },
    filters: {
      getSearchTypeName(type) {
        if(type === TYPES_ANNOUNCE_BAND) {
          return 'Groupe';
        }

        if(type === TYPES_ANNOUNCE_MUSICIAN) {
          return 'Musicien';
        }
      }
    }
  }
</script>