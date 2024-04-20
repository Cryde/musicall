<template>
  <router-link class="card is-block is-clickable" :to="to" tag="a">
    <span class="card-image" v-if="topImage">
      <span class="image card-image">
        <img :src="topImage" :class="topImageClass"/>
      </span>
    </span>
    <span class="card-content is-block" :class="cardContentClass">
      <slot name="top-content"></slot>
      <span class="content">
        <slot name="content"></slot>
      </span>
    </span>
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
    },
    topImageClass: {
      type: String,
      required: false,
      default: ''
    },
    cardContentClass: {
      type: String,
      required: false,
      default: ''
    }
  }
}
</script>

<style>
.card-content .description {
  font-size: 0.8em;
}
</style>