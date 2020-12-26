<template>
  <b-loading v-if="isLoading" active/>
  <div v-else>

    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'publication'}, label: 'Publications'}"
        :current="{label: 'Photos'}"
    />

    <h1 class="subtitle is-3">Photos</h1>

    <vue-masonry-wall :items="galleries" :options="{padding: 5}" class="mt-4" @append="append">
      <template v-slot:default="{item: gallery}">
        <card
            :key="gallery.id"
            :top-image="gallery.cover_image.sizes.medium"
            :to="{name: 'gallery_show', params: {slug: gallery.slug}}">
          <template #top-content>
            {{ gallery.title.toUpperCase() }}
          </template>
          <template #content>
            <span class="publication-date is-block mt-1">
              {{ gallery.author.username }} •
              {{ gallery.publication_datetime | relativeDate({differenceLimit: 12, showHours: false}) }} •
              {{ gallery.image_count }} photos
            </span>
          </template>
        </card>
      </template>
    </vue-masonry-wall>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import Breadcrumb from "../../../components/global/Breadcrumb";
import Card from "../../../components/global/content/Card";
import VueMasonryWall from "vue-masonry-wall";

export default {
  components: {Card, Breadcrumb, VueMasonryWall},
  metaInfo() {
    return {
      title: 'Photos'
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
  methods: {
    async append() {
      return this.galleries;
    },
  }
}
</script>

<style scoped>
.publication-date {
  color: #8b8b8b;
  font-size: 0.8em
}
</style>