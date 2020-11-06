<template>
  <b-modal :width="800" v-model="isOpen" has-modal-card>
    <template #default="props">
      <publication-settings-edit-form @close="props.close"/>
    </template>
  </b-modal>
</template>

<script>
import PublicationSettingsEditForm from "./PublicationSettingsEditForm";

export default {
  components: {PublicationSettingsEditForm},
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