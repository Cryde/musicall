<template>
  <div>
    <div class="columns mt-5 mb-4">
      <div class="column is-12">
        <hr/>
      </div>
    </div>
    <div class="columns">
      <div class="column is-12">
        <form-comment/>
      </div>
    </div>

    <comment-list/>
  </div>
</template>

<script>
import Form from "./Form";
import CommentList from "./CommentList";

export default {
  props: ['threadId'],
  components: {'form-comment': Form, CommentList},
  mounted() {
    this.$store.dispatch('thread/loadThread', this.threadId);
  }
}
</script>