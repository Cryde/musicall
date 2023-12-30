<template>
  <b-modal :width="400" v-model="isOpen" has-modal-card>
    <template #default="props">
      <add-publication-form @close="props.close"/>
    </template>
  </b-modal>
</template>

<script>
import AddPublicationForm from "./AddPublicationForm.vue";

export default {
  components: {AddPublicationForm},
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