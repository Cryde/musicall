<template>
  <div>
    <div v-for="i in 5">
      <b-skeleton width="23%" animated/>

      <div class="columns mb-6 mt-3 is-multiline">
        <div class="column is-4" v-for="i in 3">
          <div class="is-block card">
            <div class="card-content">
              <b-skeleton width="60%" animated/>
              <b-skeleton width="90%" animated/>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
