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
      <div class="column is-3 " v-for="gallery in galleries" :key="gallery.id">
        <div class="squared-image-container">
          <router-link :to="{name: 'gallery_show', params: {slug: gallery.slug}}"
                       class="is-block squared-image"
                       :style="{backgroundImage: `url(${gallery.coverImage.sizes.medium})`}"
          ></router-link>
        </div>

        <router-link :to="{name: 'gallery_show', params: {slug: gallery.slug}}"
                     class="is-block mt-1 mb-lg-2 has-text-dark">
          {{ gallery.title }}
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import Breadcrumb from "../../../components/global/Breadcrumb";

export default {
  components: {Breadcrumb},
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
