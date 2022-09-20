<template>
  <div class="columns">
    <div class="column is-8">
      <router-link :to="{name: 'forum_topic_item', params: {slug: topic.slug}}"
                   tag="h3" class="title is-5 mb-1 is-clickable">{{ topic.title }}
      </router-link>
      <span class="is-size-7">Par <strong>{{
          topic.author.username
        }}</strong> le {{ topic.creation_datetime | prettyDate }}</span>
    </div>
    <div class="column is-1 is-size-6">
      <b-icon icon="comment" pack="far"/>
      {{ topic.post_number }}
    </div>
    <div class="column is-3 is-size-7">
      Dernier message par <strong>{{ topic.last_post.creator.username }}</strong><br/> le {{ topic.last_post.creation_datetime | prettyDate }}
    </div>
  </div>
</template>

<script>
export default {
  props: {
    topic: {
      type: Object,
      required: true
    }
  }
}
</script>