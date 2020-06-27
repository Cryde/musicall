<template>
    <div>
        <h1>Dashboard</h1>

        <h2 class="mt-3">Publications</h2>
        <b-list-group class="mt-2 mb-4">
            <b-list-group-item :to="{name: 'admin_publications_pending'}">
                Publications en attente de validation
                <b-badge variant="primary" v-if="pendingPublicationsCount">{{ pendingPublicationsCount }}</b-badge>
            </b-list-group-item>
            <b-list-group-item :to="{name: 'admin_publication_featured'}">Publications mises en avant
            </b-list-group-item>
        </b-list-group>

        <h2>Galerie</h2>
        <b-list-group class="mt-2 mb-4">
            <b-list-group-item :to="{name: 'admin_gallery_pending'}">
                Galeries en attente de validation
                <b-badge variant="primary" v-if="pendingGalleriesCount">{{ pendingGalleriesCount }}</b-badge>
            </b-list-group-item>
            <b-list-group-item>Galerie : associer un artiste à une galerie</b-list-group-item>
            <b-list-group-item>Galerie : associer un évènement à une galerie</b-list-group-item>
        </b-list-group>

        <h2>Attributs</h2>
        <b-list-group class="mt-2 mb-4">
            <b-list-group-item :to="{name: 'admin_attribute_style_list'}">Gérer les styles de musique
            </b-list-group-item>
            <b-list-group-item :to="{name: 'admin_attribute_instrument_list'}">Gérer les instruments de musique
            </b-list-group-item>
        </b-list-group>

        <h2>Users</h2>
        <b-list-group class="mt-2 mb-4">
            <b-list-group-item>Gérer les membres</b-list-group-item>
        </b-list-group>

        <h2>Wiki</h2>
        <b-list-group class="mt-2 mb-4">
            <b-list-group-item :to="{name: 'admin_artists_list'}">Gérer les artistes</b-list-group-item>
        </b-list-group>
    </div>
</template>

<script>
  import {mapGetters} from "vuex";

  export default {
    computed: {
      ...mapGetters('notifications', ['pendingGalleriesCount', 'pendingPublicationsCount']),
    }
  }
</script>