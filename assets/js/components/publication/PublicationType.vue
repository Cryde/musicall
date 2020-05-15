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
