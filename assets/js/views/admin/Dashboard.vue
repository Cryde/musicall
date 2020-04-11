<template>
    <div>
        <h1>Dashboard</h1>

        <b-list-group class="mt-5">
            <b-list-group-item :to="{name: 'admin_publications_pending'}">Publications en attente de validation</b-list-group-item>
            <b-list-group-item :to="{name: 'admin_gallery_pending'}">Galeries en attente de validation</b-list-group-item>
            <b-list-group-item>Gérer les membres</b-list-group-item>
            <b-list-group-item>Galerie : associer un artiste à une galerie</b-list-group-item>
            <b-list-group-item>Galerie : associer un évènement à une galerie</b-list-group-item>
        </b-list-group>
    </div>
</template>