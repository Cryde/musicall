<template>
  <aside class="menu">
    <ul class="menu-list">
      <li>
        <router-link :to="{ name: 'home' }" exact-active-class="is-active">
          <i class="fas fa-home fa-fw"></i> Home
        </router-link>
      </li>
      <li>
        <router-link :to="{ name: 'publication' }" exact-active-class="is-active">
          <i class="fas fa-newspaper fa-fw"></i> Publications
        </router-link>
        <ul>
          <li v-if="isLoading"><spinner size="sm"/></li>
          <li v-else v-for="category in publicationCategories">
            <router-link :to="{name: 'publications_by_category', params: { slug: category.slug}}"
                         exact-active-class="is-active">
              {{ category.title }}
            </router-link>
          </li>
          <li v-if="!isLoading">
            <router-link :to="{name: 'gallery_list'}" exact-active-class="is-active">Photos</router-link>
          </li>
        </ul>
      </li>
      <li>
        <router-link :to="{ name: 'course_index' }" exact-active-class="is-active">
          <i class="fas fa-graduation-cap fa-fw"></i> Cours
        </router-link>
        <ul>
          <li v-if="isLoading"><spinner size="sm"/></li>
          <li v-else v-for="category in courseCategories">
            <router-link :to="{name: 'course_by_category', params: { slug: category.slug}}"
                         exact-active-class="is-active">
              {{ category.title }}
            </router-link>
          </li>
        </ul>
      </li>
      <li>
        <router-link :to="{ name: 'search_index' }" exact-active-class="is-active">
          <i class="fas fa-stream fa-fw"></i> Recherche
        </router-link>
      </li>
      <li>
        <router-link :to="{ name: 'forum_index' }" exact-active-class="is-active">
          <i class="far fa-comments fa-fw"></i> Forum
        </router-link>
      </li>
    </ul>
  </aside>
</template>

<script>
import {mapGetters} from 'vuex';
import {EVENT_TOGGLE_MENU} from "../../constants/events";
import Spinner from '../global/misc/Spinner.vue';

export default {
  components: {Spinner},
  computed: {
    ...mapGetters('publicationCategory', [
      'isLoading',
      'publicationCategories',
      'courseCategories'
    ])
  },
  mounted() {
    this.$root.$on(EVENT_TOGGLE_MENU, () => {
      if ([...this.$refs['menu-nav'].classList].includes('d-none')) {
        this.$refs['menu-nav'].classList.remove('d-none');
      } else {
        this.$refs['menu-nav'].classList.add('d-none');
      }
    });
  }
}
</script>