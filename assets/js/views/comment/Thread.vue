<template>
    <div>
        <b-row class="mt-5 mb-4">
            <b-col cols="12">
                <hr/>
            </b-col>
        </b-row>
        <b-row>
            <b-col cols="10" offset="1">
                <form-comment/>
            </b-col>
        </b-row>

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