<template>
  <b-loading v-if="isLoading" active/>
  <div v-else>

    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'publication'}, label: 'Publications'}"
        :current="{label: 'Photos'}"
    />

    <h1 class="subtitle is-3">Photos</h1>

    <masonry-wall :items="galleries" :column-width="300"  :gap="12" class="mt-4">
      <template #default="{ item: gallery, index }">
        <card
            :key="gallery.id"
            :top-image="gallery.cover_image"
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
    </masonry-wall>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import Breadcrumb from "../../../components/global/Breadcrumb";
import Card from "../../../components/global/content/Card";
import MasonryWall from '@yeger/vue2-masonry-wall'

export default {
  components: {Card, Breadcrumb, MasonryWall},
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
  }
}
</script>

<style scoped>
.publication-date {
  color: #8b8b8b;
  font-size: 0.8em
}
</style>