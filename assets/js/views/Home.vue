<template>
    <div v-if="isLoading" class="text-center">
        <b-spinner variant="primary"></b-spinner>
    </div>
    <div v-else>
        <featured-list/>
        <b-row :class="{'mt-5': hasFeatured}">
            <b-col cols="6">
                <h2>Dernières publications</h2>
            </b-col>
            <b-col cols="6">
                <div class="float-right" v-if="isAuthenticated">
                    <b-button variant="success" class="youtube-btn" pill size="sm"
                              v-b-modal.modal-video-add
                              v-b-tooltip.noninteractive.hover title="Ajouter une vidéo YouTube">
                        <i class="fab fa-youtube"></i>
                    </b-button>
                </div>
            </b-col>
        </b-row>

        <publication-list/>
        <add-video-modal v-if="isAuthenticated"/>
    </div>
</template>
<script>
  import {mapGetters} from 'vuex';
  import FeaturedList from "./home/FeaturedList";
  import PublicationList from "../components/publication/list/PublicationList";
  import AddVideoModal from "../components/publication/user/list/AddVideoModal";

  export default {
    components: {PublicationList, FeaturedList, AddVideoModal},
    computed: {
      ...mapGetters('security', ['isAuthenticated']),
      ...mapGetters('featured', ['hasFeatured', 'isLoading'])
    },
    mounted() {
      this.$store.dispatch('featured/loadFeatured');
    }
  }
</script>
