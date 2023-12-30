<template>
  <article class="media">
    <figure class="media-left">
      <avatar :user="comment.author"/>
    </figure>
    <div class="media-content">
      <div class="content box ">
        <p>
          <strong>{{ comment.author.username }}</strong> <small>{{ comment.creation_datetime | relativeDate }}</small>
          <br>
          <span v-html="comment.content"></span>
        </p>
      </div>
    </div>
  </article>
</template>

<script>
  import Avatar from "../../components/user/Avatar.vue";
  export default {
    components: {Avatar},
    props: ['comment']
  }
</script>