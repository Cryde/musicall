<template>
  <b-modal size="650" has-modal-card v-model="isOpen">
    <template #default="props">
      <edit-gallery-properties-form @close="props.close"/>
    </template>
  </b-modal>

</template>
<script>
import EditGalleryPropertiesForm from "./EditGalleryPropertiesForm.vue";

export default {
  components: {EditGalleryPropertiesForm},
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