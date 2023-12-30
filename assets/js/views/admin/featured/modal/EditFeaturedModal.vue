<template>
  <b-modal width="700" has-modal-card v-model="isOpen">
    <template #default="props">
      <edit-featured-form :featured="featured" @close="props.close"/>
    </template>
  </b-modal>
</template>

<script>
import EditFeaturedForm from "./EditFeaturedForm.vue";

export default {
  components: {EditFeaturedForm},
  props: ['featured'],
  data() {
    return {
      isOpen: false,
    }
  },
  methods: {
    open() {
      this.isOpen = true;
    },
  }
}
</script>