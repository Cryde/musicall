<template>
    <b-row>
        <b-col :cols="12">
            <b-button variant="primary" v-b-modal.modal-artist-add class="float-right">
                <i class="fas fa-plus"></i>
                Ajouter artiste
            </b-button>
            <h1>
                <b-link :to="{name: 'admin_dashboard'}">Admin</b-link>
                / Liste des artistes
            </h1>
        </b-col>

        <b-col cols="12" v-if="artists.length">
            <b-list-group class="mt-4 mb-4">
                <b-list-group-item v-for="artist in artists" :key="artist.id">
                    {{ artist.name }}
                    <b-button
                            :to="{name: 'admin_artists_edit', params: {id: artist.id}}"
                            size="sm" variant="primary" class="ml-3" title="Editer" v-b-tooltip>
                        <i class="far fa-edit"></i>
                    </b-button>

                    <b-button
                            :to="{name: 'artist_show', params: {slug: artist.slug}}"
                            size="sm" variant="success" class="ml-3" title="Voir" v-b-tooltip>
                        <i class="far fa-eye"></i>
                    </b-button>
                </b-list-group-item>
            </b-list-group>
        </b-col>

        <add-modal/>
    </b-row>
</template>

<script>
  import AddModal from "./modals/AddModal";
  import {EVENT_ADMIN_ADD_ARTIST} from "../../../constants/events";
  import artistApi from "../../../api/admin/artist";

  export default {
    components: {AddModal},
    data() {
      return {
        artists: [],
      }
    },
    mounted() {
      this.load();

      this.$root.$on(EVENT_ADMIN_ADD_ARTIST, () => {
        this.load();
      });
    },
    methods: {
      async load() {
        this.artists = await artistApi.listArtists();
      }
    }
  }
</script>