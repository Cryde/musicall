<template>
  <b-modal :width="600" id="modal-send-message" has-modal-card
           v-model="isModalOpen">
    <template #default="props">
      <form-send-message @close="props.close" :selected-recipient="selectedRecipient" :show-is-sent-confirmation="showIsSentConfirmation"/>
    </template>
  </b-modal>
</template>

<script>
import FormSendMessage from "./FormSendMessage";

export default {
  components: {FormSendMessage},
  props: {
    selectedRecipient: {
      default: null,
    },
    showIsSentConfirmation: {
      default: false,
      type: Boolean
    }
  },
  data() {
    return {
      isModalOpen: false,
    }
  },
  methods: {
    open() {
      this.isModalOpen = true;
    },
  }
}
</script>