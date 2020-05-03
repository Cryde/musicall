<template>
    <div v-if="isLoading" class="text-center pt-5"><b-spinner variant="primary" label="Chargement"></b-spinner></div>
    <div v-else>
        <h1>Cours</h1>
        <b-row class="mt-5 course-categories">
            <b-col tag="b-link"
                   :to="{name:'course_by_category', params: {slug: category.slug}}"
                   :cols="3"
                   v-for="category in courseCategories"
                   :key="category.order"
                   class="text-center mb-3">
                <b-img :src="`/build/images/cours/${category.slug}.png`" fluid/>
                <span class="d-block mt-3 text-uppercase">{{ category.title }}</span>
            </b-col>
        </b-row>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    computed: {
      ...mapGetters('publicationCategory', ['isLoading', 'courseCategories'])
    },
    mounted() {
      this.$store.dispatch('publicationCategory/getCategories');
    }
  }
</script>

<style>
    .course-categories a {
        color: black;
    }
</style>