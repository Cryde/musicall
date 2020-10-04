<template>
  <span v-if="isLoading"><b-spinner/></span>
  <b-avatar v-else-if="!userPicture" :text="user.username[0].toLocaleUpperCase()" :size="size"></b-avatar>
  <b-avatar v-else :src="userPicture" :size="size"></b-avatar>
</template>

<script>
export default {
  props: {
    user: Object,
    isLoading: Boolean,
    size: String
  },
  computed: {
    userPicture() {
      if (this.user) {
        if (this.user.picture) {
          return this.user.picture.small;
        }
      }
      return false;
    }
  },
}
</script>