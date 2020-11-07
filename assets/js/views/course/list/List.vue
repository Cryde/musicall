<template>
  <div>
    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'course_index'}, label: 'Cours'}"
        :current="{label: pageTitle}"
    />

    <h1 class="subtitle is-3 mb-4">{{ pageTitle }}</h1>

    <b-table :data="data" :loading="isLoading" mobile-cards>
      <b-table-column field="title" label="Titre" sortable v-slot="props">
        <router-link :to="{name: 'course_show', params: {slug: props.row.slug}}">
          {{ props.row.title }}
        </router-link>
      </b-table-column>

      <b-table-column field="author_username" label="Auteur" sortable v-slot="props">
        {{ props.row.author_username }}
      </b-table-column>

      <b-table-column field="publication_datetime" label="Date" sortable v-slot="props">
        {{ props.row.publication_datetime | relativeDate }}
      </b-table-column>

      <template #empty>
        <div class="has-text-centered" v-if="!isLoading">
          Il n'y a pas de contenu dans cette catégorie pour l'instant
        </div>
      </template>
    </b-table>
  </div>
</template>

<script>
import {mapGetters} from "vuex";
import Breadcrumb from "../../../components/global/Breadcrumb";

export default {
  components: {Breadcrumb},
  metaInfo() {
    return {
      title: this.pageTitle,
    }
  },
  data() {
    return {
      currentCategory: null,
      data: [],
    }
  },
  computed: {
    pageTitle() {
      return this.currentCategory ? this.currentCategory.title : '';
    },
    ...mapGetters('publications', [
      'publications',
      'isLoading',
      'numberOfPages'
    ]),
    ...mapGetters('publicationCategory', ['courseCategories'])
  },
  watch: {
    '$route': 'fetchData'
  },
  mounted() {
    const slug = this.$route.params.slug;
    this.currentCategory = this.courseCategories.find((category) => category.slug === slug);

    this.loadCourse();
  },
  methods: {
    fetchData() {
      const slug = this.$route.params.slug;
      this.currentCategory = this.courseCategories.find((category) => category.slug === slug);
      this.loadCourse();
    },
    async loadCourse() {
      try {
        const slug = this.$route.params.slug;
        await this.$store.dispatch('publications/getPublicationsByCategory', {slug, offset: 0});
        console.log(this.publications);

        this.data = this.publications;
      } catch (e) {
        console.error(e);
        return [];
      }
    },
  }
}
</script>