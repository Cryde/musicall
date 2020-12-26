<template>
  <b-loading v-if="isLoading" active/>
  <div v-else id="publication-list">

    <breadcrumb
        v-if="currentCategory && !this.isHome"
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'publication'}, label: 'Publications'}"
        :current="{label: currentCategory.title}"
    />
    <breadcrumb
        v-else-if="!this.isHome"
        :root="{to: {name: 'home'}, label: 'Home'}"
        :current="{label: 'Publications'}"
    />

    <h1 class="subtitle is-3" v-if="currentCategory && !this.isHome">{{ currentCategory.title }}</h1>
    <h1 class="subtitle is-3" v-else-if="!this.isHome">Publications</h1>
    <vue-masonry-wall :items="publications" :options="{padding: 5}" class="mt-4" @append="append">
      <template v-slot:default="{item: publication}">
        <card
            :key="publication.id"
            v-if="publication.category.slug !== 'news'"
            :top-image="publication.cover_image"
            :to="{ name: publication.is_course ? 'course_show' : 'publication_show', params: { slug: publication.slug }}">

          <template #top-content>
            <publication-type
                v-if="isHome"
                :type="publication.type"
                :label="publication.category.title"
                :icon="publication.type === 'video' ? 'fab fa-youtube' : 'far fa-file-alt'"
                class="mt-1 mb-2 "/>
            <publication-type
                v-else
                :type="publication.type"
                :label="publication.type === 'video' ? 'Vidéo': 'Article'"
                :icon="publication.type === 'video' ? 'fab fa-youtube' : 'far fa-file-alt'"
                class="mt-1 mb-2 "/>
            {{ publication.title }}
          </template>
          <template #content>
            <span v-if="publication.type === 'text'" class="is-block description">
              {{ publication.description }}
            </span>
            <span class="publication-date is-block mt-1">
              {{ publication.author_username }} •
              {{ publication.publication_datetime | relativeDate({differenceLimit: 12, showHours: false}) }}
            </span>
          </template>
        </card>
        <card v-else :to="{ name: 'publication_show', params: { slug: publication.slug }}">
          <template #top-content>
            <publication-type
                type="news"
                label="News"
                icon="fab fa-hotjar"
                class="mb-3"/>
            {{ publication.title }}
          </template>
          <template #content>
            <span class="description is-block">
              {{ publication.description }}
            </span>
            <span class="publication-date is-block mt-1">
              {{ publication.author_username }} •
              {{ publication.publication_datetime | relativeDate({differenceLimit: 12, showHours: false}) }}
            </span>
          </template>
        </card>
      </template>
    </vue-masonry-wall>
    <div class="overflow-auto mt-3" v-if="numberOfPages > 1">
      <b-pagination
          :total="total"
          :per-page="limitByPage"
          v-model="current">
        <b-pagination-button
            slot-scope="props"
            :page="props.page"
            :id="`page${props.page.number}`"
            tag="router-link"
            :to="props.page.number === 1 ? '?' : `?page=${props.page.number}`">
          {{ props.page.number }}
        </b-pagination-button>
      </b-pagination>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import PublicationType from "../../../components/publication/PublicationType";
import VueMasonryWall from "vue-masonry-wall";
import Card from "../../../components/global/content/Card";
import Spinner from "../../../components/global/misc/Spinner";
import {EVENT_PUBLICATION_CREATED} from "../../../constants/events";
import Breadcrumb from "../../../components/global/Breadcrumb";

export default {
  components: {Breadcrumb, Spinner, PublicationType, VueMasonryWall, Card},
  data() {
    return {
      macy: null,
      isHome: false,
      currentCategory: null,
      current: 1,
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
      'numberOfPages',
      'total',
      'limitByPage'
    ]),
    ...mapGetters('publicationCategory', ['publicationCategories'])
  },
  watch: {
    $route() {
      this.current = this.$route.query.page ? +this.$route.query.page : 1;
      this.fetchData();
    },
  },
  async created() {
    this.current = this.$route.query.page ? +this.$route.query.page : 1;
    this.isHome = this.$route.name === 'home';

    await this.fetchData();

    this.$root.$on(EVENT_PUBLICATION_CREATED, async () => {
      await this.fetchData();
    });
  },
  methods: {
    async append() {
      return this.publications;
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
  },
  beforeDestroy() {
    this.currentCategory = null;
    this.$root.$off(EVENT_PUBLICATION_CREATED);
  }
}
</script>

<style scoped>
.publication-date {
  color: #8b8b8b;
  font-size: 0.8em
}
</style>