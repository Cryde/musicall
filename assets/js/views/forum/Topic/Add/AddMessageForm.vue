<template>
  <div>
    <b-loading v-if="isSending" active/>
    <message-editor @content-update="onUpdateContent" ref="message-editor"/>

    <b-button
        class="mt-3 is-pulled-right"
        type="is-success"
        @click="submit"
        :loading="isSending"
        :disabled="!canSubmit"
        icon-left="paper-plane">
      Envoyer
    </b-button>
  </div>
</template>

<script>
import MessageEditor from "./MessageEditor.vue";
import {FORUM_MIN_LENGTH_MESSAGE} from "../../../../constants/forum";
import forumApi from "../../../../api/forum/forum";
import {EVENT_MESSAGE_CREATED} from "../../../../constants/events";

export default {
  components: {MessageEditor},
  props: ['topic'],
  data() {
    return {
      isSent: false,
      isSending: false,
      contentHTML: '',
      contentText: ''
    }
  },
  computed: {
    canSubmit() {
      return this.contentText.trim().length > FORUM_MIN_LENGTH_MESSAGE;
    }
  },
  methods: {
    async submit() {
      this.isSending = true;
      try {
        const post = await forumApi.postPostMessage({
          topic: `/api/forum_topics/${this.topic.slug}`,
          content: this.contentHTML
        });
        this.isSent = true;
        this.$root.$emit(EVENT_MESSAGE_CREATED, {post});
        this.$refs['message-editor'].reset();
      } catch (e) {
        // todo display errors
      }
      this.isSending = false;
    },
    onUpdateContent(content) {
      this.contentHTML = content.html;
      this.contentText = content.text;
    }
  }
}
</script>