<template>
  <div>
    <h2 class="title is-4">{{ category.title }}</h2>
    {{ category.description }}

    <div class="columns mb-6 is-multiline">
      <forum
          v-for="forum in category.forums"
          :key="forum.id"
          :forum="forum"/>
    </div>
  </div>
</template>

<script>
import Forum from "../Forum/Forum.vue";

export default {
  components: {Forum},
  props: {
    category: {
      type: Object,
      required: true
    }
  }
}
</script>