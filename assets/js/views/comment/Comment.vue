<template>
    <b-row class="p-3 mt-2 ">
        <b-col cols="2" class="text-center">
            <avatar :user="comment.author" size="4em"/>
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
  import Avatar from "../../components/user/Avatar";
  export default {
    components: {Avatar},
    props: ['comment']
  }
</script>