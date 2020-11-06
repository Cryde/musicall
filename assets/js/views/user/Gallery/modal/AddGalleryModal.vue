<template>
  <b-modal id="modal-gallery-add" size="500" v-model="isOpen" has-modal-card>
    <template #default="props">
      <add-gallery-form @close="props.close"></add-gallery-form>
    </template>
  </b-modal>
</template>

<script>
import AddGalleryForm from "./AddGalleryForm";

export default {
  components: {AddGalleryForm},
  data() {
    return {
      isOpen: false,
    }
  },
  methods: {
    open() {
      this.isOpen = true;
    }
  }
}
</script>