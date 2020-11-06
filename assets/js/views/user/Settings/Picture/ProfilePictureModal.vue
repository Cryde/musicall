<template>
  <b-modal id="profile-picture-modal" width="800" has-modal-card v-model="isModalOpen">
    <template #default="props">
      <form-change-picture :image="image" @close="props.close"/>
    </template>
  </b-modal>
</template>

<script>
import FormChangePicture from "./FormChangePicture";

export default {
  components: {FormChangePicture},
  props: {
    image: String
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
  },
}
</script>