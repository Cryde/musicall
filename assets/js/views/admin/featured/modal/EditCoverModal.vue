<template>
  <b-modal width="800" has-modal-card v-model="isOpen">
    <template #default="props">
      <edit-cover-form :featured="featured" @close="props.close"/>
    </template>
  </b-modal>
</template>

<script>
import EditCoverForm from "./EditCoverForm.vue";

export default {
  components: {EditCoverForm},
  props: ['featured'],
  data() {
    return {
      isOpen: false,
    };
  },
  methods: {
    open() {
      this.isOpen = true;
    },
  }
};
</script>