<template>
    <div v-if="isLoading" class="text-center pt-5">
        <b-spinner variant="primary" label="Spinning"></b-spinner>
    </div>
    <b-row v-else class="mt-4">
        <b-col cols="10" offset="1">
            <div class="comment-count mb-3 text-right" v-if="thread.comment_number === 0">Il n'y a pas encore de commentaires</div>
            <div class="comment-count mb-3 text-right" v-else-if="thread.comment_number === 1">1 commentaire</div>
            <div class="comment-count mb-3 text-right" v-else>{{thread.comment_number}} commentaires</div>

            <comment v-for="comment in comments" :comment="comment" :key="comment.id"/>
        </b-col>
    </b-row>
</template>

<script>
  import {mapGetters} from "vuex";
  import Comment from "./Comment";

  export default {
    components: {Comment},
    computed: {
      ...mapGetters('thread', ['comments', 'thread', 'isLoading'])
    },
  }
</script>