<template>
  <div>
    <template v-if="isLoading">
      <b-skeleton height="320px" class="mb-4"></b-skeleton>
    </template>
    <featured-list v-else/>
    <div class="columns">
      <div class="column is-9">
        <div class="columns" :class="{'mt-5': hasFeatured || isLoading}">
          <div class="column is-12">
            <h2 class="subtitle is-4">
              Dernières publications
              <b-tooltip label="Ajouter une vidéo YouTube" type="is-dark" class="ml-4 mt-1">
                <b-button v-if="isAuthenticated" class="youtube-btn" rounded
                          icon-left="youtube" icon-pack="fab" size="is-small"
                          @click="$refs['modal-video-add'].open()">
                  Ajouter
                </b-button>
              </b-tooltip>
            </h2>
          </div>
        </div>
        <publication-list/>
      </div>
      <div class="column is-3">
        <div class="columns" :class="{'mt-5': hasFeatured || isLoading}">
          <div class="column is-12">
            <h2 class="subtitle is-4">
              Dernières annonces
              <b-tooltip label="Dernières announces de musiciens ou groupes" class="is-size-7" type="is-info">
                <b-icon icon="info-circle" size="is-small" />
              </b-tooltip>
            </h2>

            <last-announce-list class="pt-1"/>
          </div>
        </div>
      </div>
    </div>
    <add-video-modal v-if="isAuthenticated" ref="modal-video-add"/>
  </div>
</template>
<script>
import {mapGetters} from 'vuex';
import FeaturedList from "./home/FeaturedList";
import PublicationList from "./publication/list/List.vue";
import AddVideoModal from "./user/Publication/add/video/AddVideoModal";
import Spinner from "../components/global/misc/Spinner";
import LastAnnounceList from "./announce/LastAnnounceList.vue";

export default {
  components: {LastAnnounceList, Spinner, PublicationList, FeaturedList, AddVideoModal},
  computed: {
    ...mapGetters('security', ['isAuthenticated']),
    ...mapGetters('featured', ['hasFeatured', 'isLoading'])
  },
  metaInfo() {
    return {
      title: 'MusicAll, le site de référence au service de la musique'
    }
  },
  created() {
    this.$store.dispatch('featured/loadFeatured');
  }
}
</script>
