<template>
    <span v-if="type === 'text'" class="publication-type text">
        <i class="far fa-file-alt mr-1"></i> Article
    </span>
    <span v-else-if="type === 'video'" class="publication-type video">
        <i class="fab fa-youtube mr-1"></i> Video
    </span>
    <span v-else-if="type === 'news'" class="publication-type news">
        <i class="fab fa-hotjar mr-1"></i> News
    </span>
    <span v-else></span>
</template>

<script>
  export default {
    props: ['type']
  }
</script>
