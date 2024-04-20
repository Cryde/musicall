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

    <div class="columns">
      <div class="column is-8">
        <div class="columns mb-0">
          <div class="column is-12 pb-0">
              <h2 class="subtitle is-4" v-if="currentCategory && !this.isHome">{{ currentCategory.title }}</h2>
              <h2 class="subtitle is-4" v-else-if="!this.isHome">Publications</h2>
          </div>
        </div>

        <div class="has-text-right mt-2 mb-2">
          <b-tooltip label="Ajouter une publication" type="is-dark">
            <b-button size="is-small" rounded icon-left="pen" icon-pack="fas"
                      @click="openAddPublicationModal()">
              Ajouter un publication
            </b-button>
          </b-tooltip>
        </div>

        <div v-for="publication in publications">
          <card-horizontal
              :key="publication.id"
              :image="publication.cover"
              :to="{ name: publication.sub_category.is_course ? 'course_show' : 'publication_show', params: { slug: publication.slug }}">
            <template #title>
              {{ publication.title }}
            </template>
            <template #content>
            <span v-if="publication.type_label === 'text'" class="is-block description is-size-7">
              {{ publication.description }}
            </span>
              <span class="publication-date is-block mt-2 is-size-7">
              par {{ publication.author.username }} le
            {{ publication.publication_datetime | relativeDate({differenceLimit: 12, showHours: false}) }}
            <span class="is-inline-block ml-3 mr-3 is-size-7">•</span>
            <publication-type
                :type="publication.type_label"
                :label="publication.sub_category.title"
                :icon="publication.type_label === 'video' ? 'fab fa-youtube' : 'far fa-file-alt'"
                class="is-inline-block is-size-7"/>
          </span>
            </template>
          </card-horizontal>
        </div>
      </div>

      <div class="column is-4">
        <div class="columns mb-0">
          <div class="column is-12 pb-0">
          </div>
        </div>
      </div>
    </div>

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
import PublicationType from "../../../components/publication/PublicationType.vue";
import Card from "../../../components/global/content/Card.vue";
import Spinner from "../../../components/global/misc/Spinner.vue";
import {EVENT_PUBLICATION_CREATED} from "../../../constants/events";
import Breadcrumb from "../../../components/global/Breadcrumb.vue";
import {PUBLICATION_MAX_ITEMS_PER_PAGE} from "../../../constants/publication";
import CardHorizontal from "../../../components/global/content/CardHorizontal.vue";

export default {
  components: {CardHorizontal, Breadcrumb, Spinner, PublicationType, Card},
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
    ...mapGetters('security', ['isAuthenticated']),
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
    openAddPublicationModal() {
      if (!this.isAuthenticated) {
        this.openRegisterOrLoginModal(`
        Si vous souhaitez ajouter une publication, vous devez vous connecter.<br/>
        Si vous ne disposez pas de compte, vous pouvez vous inscrire gratuitement sur le site.`);
        return;
      }

      this.$router.push({name: 'user_publications'})
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