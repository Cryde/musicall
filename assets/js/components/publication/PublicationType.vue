<template>
    <span v-if="type === 'text'" class="publication-type text">
        <i class="far fa-file-alt"></i> Article
    </span>
    <span v-else-if="type === 'video'" class="publication-type video">
        <i class="fab fa-youtube"></i> Video
    </span>
    <span v-else></span>
</template>

<script>
  export default {
    props: ['type']
  }
</script>

<style>
    .publication-type {
        border-radius: 4px;
        padding: 3px 6px;
        font-size: 0.8em;
        display: inline-block;
        text-transform: uppercase;
        color: white;
    }

    .publication-type.text {
        background: #5b87ae;
    }

    .publication-type.video {
        background: #e9573f;
    }
</style>