<template>
  <div v-if="isLoading" class="has-text-centered pt-5">
    <spinner/>
  </div>
  <div v-else class="columns mt-4">
    <div class="column is-10 is-offset-1">
      <div class="comment-count mb-3 has-text-right" v-if="totalComments === 0">
        Il n'y a pas encore de commentaires
      </div>
      <div class="comment-count mb-3 has-text-right" v-else-if="totalComments === 1">1 commentaire</div>
      <div class="comment-count mb-3 has-text-right" v-else>{{ totalComments }} commentaires</div>

      <comment v-for="comment in comments" :comment="comment" :key="comment.id" class="mb-5"/>
    </div>
  </div>
</template>

<script>
import {mapGetters} from "vuex";
import Comment from "./Comment.vue";
import Spinner from "../../components/global/misc/Spinner.vue";

export default {
  components: {Spinner, Comment},
  computed: {
    ...mapGetters('thread', ['comments', 'thread', 'isLoading', 'totalComments'])
  },
}
</script>