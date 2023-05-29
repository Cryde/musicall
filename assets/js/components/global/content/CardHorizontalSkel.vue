<template>
  <div
      class="card-horizontal box is-flex is-clickable mb-2 p-0 is-flex-direction-row">

    <div class="card-horizontal-image">
      <b-skeleton height="130px"></b-skeleton>
    </div>

    <div class="card-content-container p-5">
      <div class="content-header is-flex is-align-items-baseline">
        <h3 class="title is-5 mb-2 is-text-overflow">
          <b-skeleton width="40%" animated></b-skeleton>
        </h3>
      </div>
      <p class="">
        <b-skeleton width="80%" animated></b-skeleton>
        <b-skeleton width="80%" animated></b-skeleton>
      </p>
    </div>
  </div>
</template>

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