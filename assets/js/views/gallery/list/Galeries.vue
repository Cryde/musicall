<template>
    <div v-if="isLoading" class="text-center pt-5">
        <b-spinner variant="primary" label="Spinning"></b-spinner>
    </div>
    <div v-else>
        <h1>Galeries</h1>

        <b-row :cols="1" :cols-md="2" :cols-lg="2" :cols-xl="3">
            <b-col v-for="gallery in galleries" :key="gallery.id">

                <div class="squared-image-container">
                    <router-link :to="{name: 'gallery_show', params: {slug: gallery.slug}}"
                    class="d-block squared-image"
                                 :style="{backgroundImage: `url(${gallery.coverImage.sizes.medium})`}"
                    > </router-link>
                </div>

                <router-link :to="{name: 'gallery_show', params: {slug: gallery.slug}}" class="d-block mt-1 mb-lg-2 gallery-title">
                    {{ gallery.title }}
                </router-link>
            </b-col>
        </b-row>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    metaInfo() {
      return {
        title: 'Galeries'
      }
    },
    async created() {
      await this.$store.dispatch('galleries/loadGalleries');
    },
    computed: {
      ...mapGetters('galleries', [
        'isLoading',
        'galleries',
      ])
    },
  }
</script>

<style scoped>
    .squared-image-container {
        width: 100%;
        padding-bottom: 100%;
        margin: 5px auto;
        position: relative;
        overflow: hidden;
    }

    .squared-image {
        width: 100%;
        height: 100%;
        position: absolute;
        background-position: 50%;
        background-repeat: no-repeat;
        background-size: cover;
    }

    .gallery-title {
        color: #666
    }
</style>
