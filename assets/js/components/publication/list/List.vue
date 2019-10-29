<template>
    <div v-if="isLoading" class="text-center">
        <b-spinner variant="primary"></b-spinner>
    </div>
    <div v-else id="publication-list">
        <h1 v-if="currentCategory && !this.isHome">{{ currentCategory.title }}</h1>
        <h1 v-else-if="!this.isHome">Publications</h1>
        <div class="overflow-auto" v-if="numberOfPages > 1">
            <b-pagination-nav :link-gen="linkGen" :number-of-pages="numberOfPages" use-router align="right"
                              size="sm"></b-pagination-nav>
        </div>
        <b-card-group columns>
            <div v-for="publication in publications" :key="publication.id">
                <b-card v-if="publication.category !== 'news'" tag="b-link"
                        :to="{ name: 'publication_show', params: { slug: publication.slug }}" :title="publication.title"
                        :img-src="publication.cover_image" img-alt="Image" img-top>
                    <b-card-text v-if="publication.type === 'text'">
                        {{ publication.description }}
                    </b-card-text>
                    <div v-if="publication.type === 'video'" class="text-center video-tag"><i
                            class="fab fa-youtube"></i></div>
                </b-card>
                <b-card v-else>
                    <b-card-title>{{ publication.title }}</b-card-title>
                    <b-card-text>
                        {{ publication.description }}
                    </b-card-text>
                </b-card>
            </div>
        </b-card-group>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    data() {
      return {
        isHome: false,
        currentCategory: null
      }
    },
    metaInfo: {
      title: 'Publications',
    },
    computed: {
      ...mapGetters('publications', [
        'publications',
        'isLoading',
        'numberOfPages'
      ]),
      ...mapGetters('publicationCategory', ['categories'])
    },
    watch: {
      '$route': 'fetchData'
    },
    async mounted() {
      this.isHome = this.$route.name === 'home';
      await this.fetchData();
    },
    methods: {
      async fetchData() {
        const slug = this.$route.params.slug;
        const offset = this.$route.query.page ? this.$route.query.page-1 : 0;
        this.currentCategory = this.categories.find((category) => category.slug === slug);
        if (slug && this.currentCategory) {
          await this.$store.dispatch('publications/getPublicationsByCategory', {slug, offset});
        } else {
          await this.$store.dispatch('publications/getPublications', {offset});
        }
      },
      linkGen(pageNum) {
        return pageNum === 1 ? '?' : `?page=${pageNum}`
      }
    }
  }
</script>