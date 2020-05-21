<template>
    <b-row class="p-3 mt-2 ">
        <b-col cols="2" class="text-center">
            <b-avatar :text="comment.author.username[0].toLocaleUpperCase()" size="4em"></b-avatar>
            <strong class="mt-2 d-block">{{ comment.author.username }}</strong>
        </b-col>
        <b-col cols="10" class="comment-box pt-1   ">
            <span class="comment-date d-inline-block mb-2" :title="comment.creation_datetime | prettyDate" v-b-tooltip.hover.noninteractive>
                {{ comment.creation_datetime | relativeDate }}
            </span>
            <p v-html="comment.content"></p>
        </b-col>
    </b-row>
</template>

<script>
  export default {
    props: ['comment']
  }
</script>