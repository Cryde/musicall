<template>
  <b-loading v-if="isLoading" active/>
  <div v-else>

    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'publication'}, label: 'Publications'}"
        :current="{label: 'Photos'}"
    />

    <h1 class="subtitle is-3">Photos</h1>
    <div class="columns is-multiline">
      <div class="column is-3" v-for="gallery in galleries">
        <card
            :key="gallery.id"
            :top-image="gallery.cover_image"
            top-image-class="photo-gallery"
            :to="{name: 'gallery_show', params: {slug: gallery.slug}}"
            card-content-class="photo-content-class"
        >
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
      </div>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import Breadcrumb from "../../../components/global/Breadcrumb.vue";
import Card from "../../../components/global/content/Card.vue";

export default {
  components: {Card, Breadcrumb},
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