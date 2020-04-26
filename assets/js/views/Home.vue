<template>
    <div v-if="isLoading" class="text-center">
        <b-spinner variant="primary"></b-spinner>
    </div>
    <div v-else>
        <featured-list/>
        <h2 :class="{'mt-5': hasFeatured}">Dernières publications</h2>
        <publication-list/>
    </div>
</template>
<script>
  import {mapGetters} from 'vuex';
  import FeaturedList from "./home/FeaturedList";
  import PublicationList from "../components/publication/list/PublicationList";

  export default {
    components: {PublicationList, FeaturedList},
    computed: {
      ...mapGetters('featured', ['hasFeatured', 'isLoading'])
    },
    mounted() {
      this.$store.dispatch('featured/loadFeatured');
    }
  }
</script>