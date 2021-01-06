<template>
  <b-modal v-model="isOpen" has-modal-card>
    <template #default="props">
      <add-musician-announce-form @close="props.close" :is-from-announce="isFromAnnounce" />
    </template>
  </b-modal>
</template>

<script>
import AddMusicianAnnounceForm from "./AddMusicianAnnounceForm";

export default {
  props: {
    isFromAnnounce: {
      type: Boolean,
      default: false,
    }
  },
  components: {AddMusicianAnnounceForm},
  data() {
    return {
      isOpen: false,
    }
  },
  methods: {
    open() {
      this.isOpen = true;
    }
  },
}
</script>