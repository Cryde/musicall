<template>
    <b-modal id="modal-errors" centered title="Erreurs lors de la publication">

        <div>Il y a un ou plusieurs erreurs lors de la publication.</div>

        <ul class="mt-3">
            <li v-for="error in errors">{{ error }}</li>
        </ul>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button @click="cancel()">
                Ok
            </b-button>
        </template>
    </b-modal>
</template>

<script>
  export default {
    props: ['errors']
  }
</script>