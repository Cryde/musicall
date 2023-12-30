<template>
  <div>
    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :current="{label: 'Forum'}"
    />

    <h1 class="subtitle is-3">Liste des forums</h1>

    <category-list-skel v-if="loading"/>
    <category-list v-else :forum-categories="forumCategories"/>
  </div>
</template>
<script>
import Breadcrumb from "../../components/global/Breadcrumb.vue";
import forum from "../../api/forum/forum";
import CategoryList from "./Category/CategoryList.vue";
import CategoryListSkel from "./Category/CategoryListSkel.vue";

export default {
  components: {CategoryListSkel, CategoryList, Breadcrumb},
  data() {
    return {
      loading: true,
      forumCategories: null,
    }
  },
  metaInfo() {
    return {
      title: 'Liste des forums - MusicAll',
    }
  },
  async mounted() {
    this.loading = true;
    this.forumCategories = await forum.getRootForumCategory();
    this.loading = false;
  }
}
</script>