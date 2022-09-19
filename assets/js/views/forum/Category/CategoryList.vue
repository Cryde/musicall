<template>
  <div>
    <category
        v-for="category in forumCategories"
        :key="category.id"
        :category="category"
    />
  </div>
</template>

<script>
import Category from "./Category";

export default {
  components: {Category},
  props: {
    forumCategories: {
      type: Array,
      required: true
    }
  }
}
</script>