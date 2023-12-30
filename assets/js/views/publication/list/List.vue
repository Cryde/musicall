<template>
  <div id="publication-list">
    <div v-if="isLoading">
      <card-horizontal-skel v-for="i in itemPerPage" :key="i"/>
    </div>
    <template v-else>
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
    </template>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import PublicationType from "../../../components/publication/PublicationType.vue";
import CardHorizontal from "../../../components/global/content/CardHorizontal.vue";
import Spinner from "../../../components/global/misc/Spinner.vue";
import {EVENT_PUBLICATION_CREATED} from "../../../constants/events";
import {PUBLICATION_MAX_ITEMS_ON_LIST_PER_PAGE} from "../../../constants/publication";
import CardHorizontalSkel from "../../../components/global/content/CardHorizontalSkel.vue";

export default {
  components: {CardHorizontalSkel, CardHorizontal, Spinner, PublicationType},
  data() {
    return {
      current: 1,
    }
  },
  computed: {
    ...mapGetters('publications', [
      'publications',
      'isLoading',
      'total',
    ]),
    displayPagination() {
      return this.total > PUBLICATION_MAX_ITEMS_ON_LIST_PER_PAGE;
    },
    itemPerPage() {
      return PUBLICATION_MAX_ITEMS_ON_LIST_PER_PAGE;
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

    await this.fetchData();

    this.$root.$on(EVENT_PUBLICATION_CREATED, async () => {
      await this.fetchData();
    });
  },
  methods: {
    async fetchData() {
      const page = this.$route.query.page ? this.$route.query.page : 1;

      await this.$store.dispatch('publications/getPublications', {page});
    },
  },
  beforeDestroy() {
    this.$root.$off(EVENT_PUBLICATION_CREATED);
  }
}
</script>

<style scoped>
.publication-date {
  color: #8b8b8b;
}
</style>