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
    <masonry-wall :items="publications" :gap="10" :column-width="250" class="mt-4">
      <template v-slot:default="{item: publication}">
        <card
            :key="publication.id"
            v-if="publication.sub_category.slug !== 'news'"
            :top-image="publication.cover"
            :to="{ name: publication.sub_category.is_course ? 'course_show' : 'publication_show', params: { slug: publication.slug }}">

          <template #top-content>
            <publication-type
                v-if="isHome"
                :type="publication.type_label"
                :label="publication.sub_category.title"
                :icon="publication.type_label === 'video' ? 'fab fa-youtube' : 'far fa-file-alt'"
                class="mt-1 mb-2 "/>
            <publication-type
                v-else
                :type="publication.type_label"
                :label="publication.type_label === 'video' ? 'Vidéo': 'Article'"
                :icon="publication.type_label === 'video' ? 'fab fa-youtube' : 'far fa-file-alt'"
                class="mt-1 mb-2 "/>
            {{ publication.title }}
          </template>
          <template #content>
            <span v-if="publication.type_label === 'text'" class="is-block description">
              {{ publication.description }}
            </span>
            <span class="publication-date is-block mt-1">
              {{ publication.author.username }} •
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
              {{ publication.author.username }} •
              {{ publication.publication_datetime | relativeDate({differenceLimit: 12, showHours: false}) }}
            </span>
          </template>
        </card>
      </template>
    </masonry-wall>
    <div class="overflow-auto mt-3" v-if="displayPagination">
      <b-pagination
          :total="total"
          :per-page="itemPerPage"
          v-model="current">
        <template #default="props">
          <b-pagination-button
              :page="props.page"
              :id="`page${props.page.number}`"
              tag="router-link"
              :to="props.page.number === 1 ? '?' : `?page=${props.page.number}`">
            {{ props.page.number }}
          </b-pagination-button>
        </template>
        <template #previous="props">
          <b-pagination-button
              :page="props.page"
              tag="router-link"
              :to="props.page.number <= 1 ? '?' : `?page=${props.page.number}`">
            <b-icon icon="angle-left"/>
          </b-pagination-button>
        </template>

        <template #next="props">
          <b-pagination-button
              :page="props.page"
              tag="router-link"
              :to="`?page=${props.page.number}`">
            <b-icon icon="angle-right"/>
          </b-pagination-button>
        </template>
      </b-pagination>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import PublicationType from "../../../components/publication/PublicationType";
import MasonryWall from '@yeger/vue2-masonry-wall'
import Card from "../../../components/global/content/Card";
import Spinner from "../../../components/global/misc/Spinner";
import {EVENT_PUBLICATION_CREATED} from "../../../constants/events";
import Breadcrumb from "../../../components/global/Breadcrumb";
import {PUBLICATION_MAX_ITEMS_PER_PAGE} from "../../../constants/publication";

export default {
  components: {Breadcrumb, Spinner, PublicationType, MasonryWall, Card},
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
      'total',
    ]),
    ...mapGetters('publicationCategory', ['publicationCategories']),
    displayPagination() {
      return this.total > PUBLICATION_MAX_ITEMS_PER_PAGE;
    },
    itemPerPage() {
      return PUBLICATION_MAX_ITEMS_PER_PAGE;
    }
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
    pageTitle() {
      if (this.isHome) {
        return 'MusicAll, le site de référence au service de la musique';
      }

      if (this.currentCategory) {
        return `${this.currentCategory.title} - MusicAll`;
      }

      return 'Toutes les publications relatives à la musique - MusicAll'
    },
    async fetchData() {
      const slug = this.$route.params.slug;
      const page = this.$route.query.page ? this.$route.query.page : 1;
      this.currentCategory = this.publicationCategories.find((category) => category.slug === slug);
      if (slug && this.currentCategory) {
        await this.$store.dispatch('publications/getPublicationsByCategory', {slug, page});
      } else {
        await this.$store.dispatch('publications/getPublications', {page});
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