<template>
  <b-modal width="500" has-modal-card v-model="isModalOpen">
    <template #default="props">
      <add-video-form @close="props.close"></add-video-form>
    </template>
  </b-modal>
</template>

<script>
import AddVideoForm from "./AddVideoForm";

export default {
  components: {AddVideoForm},
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