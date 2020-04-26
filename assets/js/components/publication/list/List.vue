<template>
    <div v-if="isLoading" class="text-center">
        <b-spinner variant="primary"></b-spinner>
    </div>
    <div v-else id="publication-list">
        <h1 v-if="currentCategory && !this.isHome">{{ currentCategory.title }}</h1>
        <h1 v-else-if="!this.isHome">Publications</h1>
        <b-card-group columns class="mt-4">
            <b-card
                    v-for="publication in publications" :key="publication.id"
                    v-if="publication.category !== 'news'" tag="b-link"
                    :to="{ name: 'publication_show', params: { slug: publication.slug }}" :title="publication.title"
                    :img-src="publication.cover_image" img-alt="Image" img-top>
                <b-card-text v-if="publication.type === 'text'">
                    {{ publication.description }}
                </b-card-text>
                <div class="publication-date mt-1">{{publication.publication_datetime | relativeDate }}</div>
                <publication-type :type="publication.type" class="mt-1 pull-right"/>
            </b-card>
            <b-card v-else
                    tag="b-link"
                    :to="{ name: 'publication_show', params: { slug: publication.slug }}"
            >
                <b-card-title>{{ publication.title }}</b-card-title>
                <b-card-text>
                    {{ publication.description }}
                </b-card-text>
                <div class="publication-date mt-1">{{publication.publication_datetime | relativeDate }}</div>
            </b-card>
        </b-card-group>
        <div class="overflow-auto" v-if="numberOfPages > 1">
            <b-pagination-nav :link-gen="linkGen" :number-of-pages="numberOfPages" use-router align="right"
                              size="sm"></b-pagination-nav>
        </div>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import PublicationType from "../PublicationType";

  export default {
    components: {PublicationType},
    data() {
      return {
        isHome: false,
        currentCategory: null
      }
    },
    metaInfo() {
      return {
        title: this.pageTitle(),
      }
    },
    computed: {
      ...mapGetters('publications', [
        'publications',
        'isLoading',
        'numberOfPages'
      ]),
      ...mapGetters('publicationCategory', ['publicationCategories'])
    },
    watch: {
      '$route': 'fetchData'
    },
    async created() {
      this.isHome = this.$route.name === 'home';
      await this.fetchData();
    },
    methods: {
      pageTitle() {
        if (this.isHome) {
          return 'MusicAll, le site de référence au service de la musique';
        }

        if (this.currentCategory) {
          return this.currentCategory.title;
        }

        return 'Toutes les publications relative à la musique | MusicAll'
      },
      async fetchData() {
        const slug = this.$route.params.slug;
        const offset = this.$route.query.page ? this.$route.query.page - 1 : 0;
        this.currentCategory = this.publicationCategories.find((category) => category.slug === slug);
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

<style scoped>
    .publication-date {
        color: #8b8b8b;
        font-size: 0.8em
    }
</style>