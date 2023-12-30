<template>
  <span v-if="isLoading"><spinner/></span>
  <div v-else-if="!userPicture" class="image circled-image has-text-centered" :class="cssClass">
    <span class="initials">{{ user.username[0].toLocaleUpperCase() }}</span>
  </div>
  <p v-else class="image" :class="cssClass">
    <img :src="userPicture" class="is-rounded">
  </p>
</template>

<script>
import Spinner from "../global/misc/Spinner.vue";

export default {
  components: {Spinner},
  props: {
    user: Object,
    isLoading: Boolean,
    size: {
      type: String,
      default: '64'
    }
  },
  computed: {
    cssClass() {
      if (this.size === '64') {
        return 'is-64x64';
      }

      if (this.size === '32') {
        return 'is-32x32';
      }

      if (this.size === '24') {
        return 'is-24x24';
      }
    },
    userPicture() {
      if (this.user) {
        if (this.user.picture) { // we will have to remove this in the futur
          return this.user.picture.small;
        }
        if (this.user.profile_picture) {
          return this.user.profile_picture.small;
        }
      }
      return false;
    }
  },
}
</script>

<style>

.circled-image {
  background-color: #ccc;
  border-radius: 50%;
  text-align: center;
}

.initials {
  line-height: 1;
  position: relative;
}

.is-24x24 .initials {
  font-size: 12px; /* 50% of parent */
  top: 1px; /* 25% of parent */
}

.is-32x32 .initials {
  font-size: 16px; /* 50% of parent */
  top: 4px; /* 25% of parent */
}

.is-64x64 .initials {
  font-size: 32px; /* 50% of parent */
  top: 16px; /* 25% of parent */
}

</style>