<template>
  <div class="box content columns mb-4">
    <div class="column is-1 is-flex is-flex-direction-column is-align-items-center">
      <avatar :user="post.creator"/>
      <span>{{ post.creator.username }}</span>
    </div>
    <div class="column is-9">
      <span class="is-size-7 is-block mb-3 has-text-grey">{{ post.creation_datetime | prettyDate }}</span>

      <div class="content" v-html="post.content"></div>
    </div>
  </div>
</template>

<script>
import Avatar from "../../../components/user/Avatar.vue";

export default {
  components: {Avatar},
  props: {
    post: {
      type: Object,
      required: true,
    }
  }
}
</script>