<template>
  <router-link class="card is-clickable" v-if="featured1"
               :to="{name:'publication_show', params: {slug: featured1.publication.slug}}"
               tag="div">
    <div class="card-image" v-if="featured1.cover">
      <figure class="image card-image is-overlay">
        <img :src="featured1.cover" :alt="featured1.title"/>
      </figure>
    </div>
    <header class="card-header is-overlay" v-if="featured1.title">
      <h2 class="subtitle is-3 mt-3 ml-5" :class="getColor(featured1)">{{ featured1.title }}</h2>
    </header>
    <div class="card-content is-overlay mt-5">
      <div class="content" v-if="featured1.description" :class="getColor(featured1)">
        {{ featured1.description }}
      </div>
    </div>
  </router-link>
</template>

<script>
import {mapGetters} from 'vuex';

export default {
  computed: {
    ...mapGetters('featured', ['featured1'])
  },
  methods: {
    getColor(featured) {
      return featured.options.color === 'dark' ? 'has-text-dark' : 'has-text-light';
    },
  }
}
</script>