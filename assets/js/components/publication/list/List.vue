<template>
    <div v-if="isLoading" class="text-center">
        <b-spinner variant="primary"></b-spinner>
    </div>
    <div v-else id="publication-list">
        <h1 v-if="currentCategory && !this.isHome">{{ currentCategory.title }}</h1>
        <h1 v-else-if="!this.isHome">Publications</h1>
        <vue-masonry-wall :items="publications" :options="{padding: 5}" class="mt-4" @append="append">
            <template v-slot:default="{item: publication}">
                <b-card
                        :key="publication.id"
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
            </template>
        </vue-masonry-wall>
        <div class="overflow-auto mt-3" v-if="numberOfPages > 1">
            <b-pagination-nav :link-gen="linkGen" :number-of-pages="numberOfPages" use-router align="right"
                              size="sm"></b-pagination-nav>
        </div>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import PublicationType from "../PublicationType";
  import VueMasonryWall from "vue-masonry-wall";

  export default {
    components: {PublicationType, VueMasonryWall},
    data() {
      return {
        macy: null,
        isHome: false,
        currentCategory: null,
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
      $route() {
        this.fetchData();
      },
    },
    async created() {
      this.isHome = this.$route.name === 'home';

      await this.fetchData();

      this.$root.$on('publication-added', async () => {
        await this.fetchData();
      });
    },
    methods: {
      async append() {
        return this.publications;
        console.log('appned !!');
        //await this.fetchData();
       // return this.publications;
      },
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
    },
    beforeDestroy() {
      this.currentCategory = null;
    }
  }
</script>

<style scoped>
    .publication-date {
        color: #8b8b8b;
        font-size: 0.8em
    }
</style>