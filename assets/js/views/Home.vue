<template>
  <b-loading v-if="isLoading" active/>
  <div v-else>
    <featured-list/>
    <div class="columns" :class="{'mt-5': hasFeatured}">
      <div class="column is-12">
        <h2 class="subtitle is-4">
          Dernières publications
          <b-tooltip label="Ajouter une vidéo YouTube" type="is-dark" class="is-pulled-right">
            <b-button v-if="isAuthenticated" class="youtube-btn" rounded
                      icon-left="youtube" icon-pack="fab"
                      @click="$refs['modal-video-add'].open()">
              Ajouter
            </b-button>
          </b-tooltip>
        </h2>
      </div>
    </div>

    <list/>
    <add-video-modal v-if="isAuthenticated" ref="modal-video-add"/>
  </div>
</template>
<script>
import {mapGetters} from 'vuex';
import FeaturedList from "./home/FeaturedList";
import List from "./publication/list/Grid.vue";
import AddVideoModal from "./user/Publication/add/video/AddVideoModal";
import Spinner from "../components/global/misc/Spinner";

export default {
  components: {Spinner, List, FeaturedList, AddVideoModal},
  computed: {
    ...mapGetters('security', ['isAuthenticated']),
    ...mapGetters('featured', ['hasFeatured', 'isLoading'])
  },
  created() {
    this.$store.dispatch('featured/loadFeatured');
  }
}
</script>
