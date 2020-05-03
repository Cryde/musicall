<template>
    <div>
        <h1>{{ pageTitle }}</h1>

        <b-table
                ref="course-table"
                :busy.sync="isBusy"
                show-empty
                borderless
                striped
                stacked="md"
                :fields="fields"
                :items="courseProvider"
        >
            <div slot="table-busy" class="text-center my-2">
                <b-spinner class="align-middle"></b-spinner>
            </div>

            <template v-slot:cell(title)="data">
                <router-link :to="{name: 'course_show', params: {slug: data.item.slug}}">
                    {{ data.item.title }}
                </router-link>
            </template>

            <template v-slot:cell(publication_datetime)="data">
                {{ data.item.publication_datetime | relativeDate }}
            </template>
        </b-table>
    </div>
</template>

<script>
  import {mapGetters} from "vuex";

  export default {
    metaInfo() {
      return {
        title: this.pageTitle,
      }
    },
    data() {
      return {
        currentCategory: null,
        currentPage: 1,
        perPage: 0,
        total: null,
        loadingControlPublication: false,
        isBusy: false,
        errors: [],
        showPublicationUrl: '',
        sortBy: '',
        sortDesc: true,
        fields: [{key: 'title', label: 'Titre'}, {
          key: 'author_username',
          label: 'Auteur'
        }, {key: 'publication_datetime', label: 'Publication'}]
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
    },
    methods: {
      fetchData() {
        this.$refs['course-table'].refresh()
      },
      async courseProvider(ctx, callback) {
        try {
          const slug = this.$route.params.slug;
          await this.$store.dispatch('publications/getPublicationsByCategory', {slug, offset: 0});
          console.log(this.publications);

          return this.publications;
        } catch (e) {
          console.error(e);
          return [];
        }
      },
    }
  }
</script>