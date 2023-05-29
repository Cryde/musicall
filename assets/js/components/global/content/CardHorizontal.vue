<template>
  <router-link
      class="card-horizontal box is-flex is-clickable mb-2 p-0 is-flex-direction-row"
      :to="to" tag="a">

    <div class="card-horizontal-image" :style="{'background-image': 'url(' + image +  ')'}"></div>

    <div class="card-content-container p-5">
      <div class="content-header is-flex is-align-items-baseline">
        <h3 class="title is-5 mb-2 is-text-overflow">
          <slot name="title"></slot>
        </h3>
      </div>
      <p class="">
        <slot name="content"></slot>
      </p>
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
    image: {
      type: String,
      required: false,
      default: ''
    }
  }
}
</script>

<style>
.card-horizontal {
}

.card-horizontal .card-horizontal-image {
  display: flex;
  justify-content: center;
  flex: 0 0 210px;
  height: auto;
  background-size: cover;
  background-position: center center;

  border-top-left-radius: 6px;
  border-bottom-left-radius: 6px;
}

.card-content-container {
  width: 0;
  flex: 1 1 auto;
}

</style>