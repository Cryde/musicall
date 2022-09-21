<template>
  <b-loading v-if="isLoading" active/>
  <div v-else id="publication-list">

    <breadcrumb
        v-if="currentCategory && !this.isHome"
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'course_index'}, label: 'Cours'}"
        :current="{label: currentCategory.title}"
    />

    <h1 class="subtitle is-3" v-if="currentCategory && !this.isHome">
      {{ currentCategory.title }}
      <b-tooltip label="Ajouter un cours vidéo YouTube" type="is-dark" class="is-pulled-right">
        <b-button v-if="isAuthenticated" class="youtube-btn" rounded
                  icon-left="youtube" icon-pack="fab"
                  @click="$refs['modal-video-add'].open()">
          Ajouter
        </b-button>
      </b-tooltip>
    </h1>
    <h1 class="subtitle is-3" v-else-if="!this.isHome">Cours</h1>
    <b-message type="is-info" v-if="publications.length === 0 && error.length === 0">
      Il n'y a pas encore de publications dans cette catégorie.
    </b-message>

    <b-message v-if="error.length > 0" type="is-danger">
      {{ error }}
    </b-message>

    <vue-masonry-wall :items="publications" :options="{padding: 5}" class="mt-4" @append="append">
      <template v-slot:default="{item: publication}">
        <card
            :key="publication.id"
            :top-image="publication.cover_image"
            :to="{ name: 'course_show', params: { slug: publication.slug }}">

          <template #top-content>
            <publication-type
                :type="publication.type"
                :label="publication.type === 'video' ? 'Vidéo' : 'Cours'"
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
    <add-video-modal v-if="isAuthenticated" ref="modal-video-add" :display-categories="true" :categories="courseCategories" :pre-selected-category="currentCategory" />
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
import AddVideoModal from "../../user/Publication/add/video/AddVideoModal";

export default {
  components: {AddVideoModal, Breadcrumb, Spinner, PublicationType, VueMasonryWall, Card},
  data() {
    return {
      macy: null,
      isHome: false,
      currentCategory: null,
      current: 1,
      error: '',
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
    ...mapGetters('security', ['isAuthenticated']),
    ...mapGetters('publicationCategory', ['courseCategories'])
  },
  watch: {
    $route() {
      this.current = this.$route.query.page ? +this.$route.query.page : 1;
      this.fetchData();
    },
  },
  async created() {
    this.current = this.$route.query.page ? +this.$route.query.page : 1;

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
      if (this.currentCategory) {
        return `${this.currentCategory.title} - MusicAll`;
      }

      return 'Toutes les cours relatifs à la musique - MusicAll'
    },
    async fetchData() {
      this.error = '';
      const slug = this.$route.params.slug;
      const offset = this.$route.query.page ? this.$route.query.page - 1 : 0;
      this.currentCategory = this.courseCategories.find((category) => category.slug === slug);
      if (slug && this.currentCategory) {
        await this.$store.dispatch('publications/getPublicationsByCategory', {slug, offset});
      } else {
        this.error = 'Cette catégorie n\'existe pas.';
      }
    },
  },
  beforeDestroy() {
    this.currentCategory = null;
    this.error = '';
  }
}
</script>

<style scoped>
.publication-date {
  color: #8b8b8b;
  font-size: 0.8em
}
</style>