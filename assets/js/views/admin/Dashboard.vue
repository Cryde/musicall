<template>
  <div>
    <h1 class="subtitle is-3">Dashboard</h1>

    <h2 class="mt-3 subtitle is-4">Publications</h2>
    <div class="mt-2 mb-4">
      <b-button tag="router-link" :to="{name: 'admin_publications_pending'}">
        Publications en attente de validation
        <b-tag type="is-warning" v-if="pendingPublicationsCount">{{ pendingPublicationsCount }}</b-tag>
      </b-button>
      <b-button tag="router-link" :to="{name: 'admin_publication_featured'}">Publications mises en avant
      </b-button>
    </div>

    <h2 class="subtitle is-4">Galerie</h2>
    <div class="mt-2 mb-4">
      <b-button tag="router-link" :to="{name: 'admin_gallery_pending'}">
        Galeries en attente de validation
        <b-tag type="is-warning" v-if="pendingGalleriesCount">{{ pendingGalleriesCount }}</b-tag>
      </b-button>
      <b-button>Galerie : associer un artiste à une galerie</b-button>
      <b-button>Galerie : associer un évènement à une galerie</b-button>
    </div>

    <h2 class="subtitle is-4">Attributs</h2>
    <div class="mt-2 mb-4">
      <b-button tag="router-link" :to="{name: 'admin_attribute_style_list'}">Gérer les styles de musique
      </b-button>
      <b-button tag="router-link" :to="{name: 'admin_attribute_instrument_list'}">Gérer les instruments de musique
      </b-button>
    </div>

    <h2 class="subtitle is-4">Users</h2>
    <div class="mt-2 mb-4">
      <b-button>Gérer les membres</b-button>
    </div>

    <h2 class="subtitle is-4">Wiki</h2>
    <div class="mt-2 mb-4">
      <b-button>Gérer les artistes</b-button>
    </div>
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