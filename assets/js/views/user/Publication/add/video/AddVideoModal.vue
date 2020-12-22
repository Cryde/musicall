<template>
  <b-modal width="500" has-modal-card v-model="isModalOpen">
    <template #default="props">
      <add-video-form @close="props.close"
                      :display-categories="displayCategories"
                      :categories="categories"
                      :pre-selected-category="preSelectedCategory"
      ></add-video-form>
    </template>
  </b-modal>
</template>

<script>
import AddVideoForm from "./AddVideoForm";

export default {
  components: {AddVideoForm},
  props: {
    displayCategories: {
      type: Boolean,
      default: false,
    },
    categories: {
      type: Array,
      default: () => []
    },
    preSelectedCategory: {
      type: Object,
      default: null
    }
  },
  data() {
    return {
      isModalOpen: false,
    }
  },
  methods: {
    open() {
      this.isModalOpen = true;
    }
  }
}
</script>