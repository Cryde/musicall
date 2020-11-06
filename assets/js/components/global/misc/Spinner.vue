<template>
  <i class="fas fa-spinner fa-spin" :class="defineSizeClass"></i>
</template>

<script>
export default {
  props: {
    size: {
      type: String,
      default: 'med'
    }
  },
  computed: {
    defineSizeClass() {
      if (this.size === 'sm') {
        return '';
      }
      if (this.size === 'med') {
        return 'fa-2x'
      }
    }
  }
}
</script>