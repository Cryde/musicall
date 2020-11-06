<template>
  <div v-if="isLoading" class="has-text-centered pt-5">
    <spinner/>
  </div>
  <div v-else>
    <h1 class="subtitle is-3">Cours</h1>
    <div class="columns mt-5 course-categories">
      <router-link
          tag="div"
          :to="{name:'course_by_category', params: {slug: category.slug}}"
          v-for="category in courseCategories"
          :key="category.order"
          class="column has-text-centered mb-3 is-clickable has-text-dark">
        <b-image :src="`/build/images/cours/${category.slug}.png`" responsive/>
        <span class="is-block mt-3 is-uppercase">{{ category.title }}</span>
      </router-link>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import Spinner from "../../components/global/misc/Spinner";

export default {
  components: {Spinner},
  computed: {
    ...mapGetters('publicationCategory', ['isLoading', 'courseCategories'])
  },
  mounted() {
    this.$store.dispatch('publicationCategory/getCategories');
  }
}
</script>
