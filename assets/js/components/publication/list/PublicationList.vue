<template>
    <div v-if="isLoading" class="text-center">
        <b-spinner variant="primary"></b-spinner>
    </div>
    <div v-else>
        <List/>
    </div>
</template>
<script>
  import {mapGetters} from 'vuex';
  import List from "./List";

  export default {
    components: {List},
    computed: {
      ...mapGetters('publicationCategory', [
        'isLoading'
      ])
    },
  }
</script>