<template>
  <router-link class="card is-clickable" :to="to" tag="div">
    <div class="card-image" v-if="topImage">
      <figure class="image card-image">
        <img :src="topImage"/>
      </figure>
    </div>
    <div class="card-content">
      <slot name="top-content"></slot>
      <div class="content">
        <slot name="content"></slot>
      </div>
    </div>
  </router-link>
</template>

<script>
export default {
  props: {
    to: {
      type: Object,
      required: true
    },
    topImage: {
      type: String,
      required: false,
      default: ''
    }
  }
}
</script>

<style>
.card-content p {
  font-size: 0.8em;
}
</style>