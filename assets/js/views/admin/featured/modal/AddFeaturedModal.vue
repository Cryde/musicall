<template>
  <b-modal :width="800" has-modal-card v-model="isOpen">
    <template #default="props">
      <add-feature-form :level="level" @close="props.close"/>
    </template>
  </b-modal>
</template>

<script>
import AddFeatureForm from "./AddFeatureForm";

export default {
  components: {AddFeatureForm},
  props: ['level'],
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