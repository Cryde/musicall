<template>
  <div v-if="isLoading" class="has-text-centered pb-3 pt-3">
    <b-loading active/>
  </div>
  <div v-else>
    <b-button @click="openAddMusicianAnnounce()"
              icon-left="bullhorn"
              type="is-info" class="is-pulled-right">
      Ajouter une nouvelle annonce
    </b-button>
    <h1 class="subtitle is-3">Mes annonces</h1>

    <b-table :data="announces" :loading="isLoading" :debounce-search="500" mobile-cards>
      <template #empty>
        <div class="has-text-centered" v-if="!isLoading">
          Vous n'avez pas encore d'annonces.
        </div>
      </template>

      <b-table-column field="type" label="Recherche" sortable v-slot="props">
        <span class="tag">{{ props.row.type | getSearchTypeName }}</span>
      </b-table-column>

      <b-table-column field="instrument.musician_name" label="Instrument" sortable v-slot="props">
        {{ props.row.instrument.musician_name }}
      </b-table-column>

      <b-table-column field="styles" label="Styles" sortable v-slot="props">
        <b-taglist>
          <b-tag type="is-info" v-for="label in props.row.styles.map(item => item.name)" :key="label"> {{
              label
            }}
          </b-tag>
        </b-taglist>
      </b-table-column>

      <b-table-column field="creation_datetime" label="Date de création" sortable v-slot="props">
        {{ props.row.creation_datetime | prettyDate }}
      </b-table-column>

      <b-table-column field="localisation" label="Localisation" sortable v-slot="props">
        {{ props.row.location_name }}
      </b-table-column>

      <b-table-column field="note" label="Note" v-slot="props">
        {{ props.row.note }}
      </b-table-column>

      <b-table-column v-slot="props">
        <b-button type="is-danger" size="is-small" icon-left="times" icon-pack="fas"
          @click="openAreYouSureDelete(props.row.id)"
        />
      </b-table-column>
    </b-table>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import {TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN} from "../../../constants/types";
import {EVENT_ANNOUNCE_MUSICIAN_CREATED} from "../../../constants/events";
import AddMusicianAnnounceForm from "./modal/AddMusicianAnnounceForm.vue";

export default {
  computed: {
    ...mapGetters('userMusicianAnnounces', ['isLoading', 'announces']),
  },
  mounted() {
    this.$store.dispatch('userMusicianAnnounces/load');
    this.$root.$on(EVENT_ANNOUNCE_MUSICIAN_CREATED, () => {
      this.$store.dispatch('userMusicianAnnounces/refresh');
    });
  },
  methods: {
    openAddMusicianAnnounce() {
      this.$buefy.modal.open({
        parent: this,
        component: AddMusicianAnnounceForm,
        props: {isFromAnnounce: true},
        hasModalCard: true,
        trapFocus: true
      })
    },
    async openAreYouSureDelete(id) {
      const { result, dialog } = await this.$buefy.dialog.confirm({
        title: 'Êtes vous sur ?',
        closeOnConfirm: false,
        cancelText: 'Annuler',
        confirmText: 'Oui',
      });

      if (!result) {
        return;
      }

      dialog.close();
      await this.$store.dispatch('userMusicianAnnounces/delete', id);
    }
  },
  filters: {
    getSearchTypeName(type) {
      if (type === TYPES_ANNOUNCE_BAND) {
        return 'Groupe';
      }

      if (type === TYPES_ANNOUNCE_MUSICIAN) {
        return 'Musicien';
      }
    }
  },
  destroyed() {
    this.$root.$off(EVENT_ANNOUNCE_MUSICIAN_CREATED);
  }
}
</script>