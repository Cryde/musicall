<template>
  <div class="column is-4">
    <router-link class="is-block card" :to="{name: 'forum_topic_list', params: {id: forum.id}}">
      <div class="card-content">
        <h4 class="title is-4 ">{{ forum.title }}</h4>
        <p class="subtitle is-6">
          {{ forum.description }}
        </p>
      </div>
    </router-link>
  </div>
</template>

<script>
export default {
  props: {
    forum: {
      type: Object,
      required: true
    }
  }
}
</script>