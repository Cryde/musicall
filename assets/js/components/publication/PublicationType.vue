<template>
  <span class="publication-type"
        :class="{'text': type ==='text', 'video' : type === 'video', 'news' : type === 'news'}">
      <i :class="icon" class="mr-1"></i> {{ label }}
  </span>
</template>

<script>
export default {
  props: ['type', 'label', 'icon']
}
</script>
