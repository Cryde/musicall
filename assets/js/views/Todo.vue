<template>
    <div>
        <h1>Todo</h1>

        <div class="content-box">
            <p>
                MusicAll est pour l'instant en phase de re-développement.<br/>
                C'est à dire que je reprends tout à partir de zero et reconstruit les briques unes à unes.<br/>
                Le <strong>design</strong> est pour l'instant relativement basique, je prévois de l'améliorer si pas le
                changer.<br><br/>

                <strong>Toutes les données que vous rentrerez ici sont suceptible de disparaitre à tout jamais plus
                    tard.</strong>
            </p>
            <p>
                <strong>Ce qui est fait :</strong>
            </p>
            <ul>
                <li>Possibilité de s'inscrire</li>
                <li>Possibilité de se connecter</li>
                <li>Création d'une publication</li>
                <li>Vue d'une publication</li>
                <li>
                    Publication
                    <ul>
                        <li>Gérer le cycle de vie d'une publication (draft - pending - online)</li>
                        <li>Mise en place du nouvel éditeur de publication</li>
                        <li>Edit d'une publication</li>
                        <li>Suppression d'une publication</li>
                        <li>Ajouter une ou plusieurs image dans la publication</li>
                        <li>Vue plus avancée de la publication</li>
                        <li>Ajouter des messages de confirmation/erreurs lors des diverse actions</li>
                        <li>Ajouter une photo de "couverture" à la publication</li>
                    </ul>
                </li>
                <li>
                    User
                    <ul>
                        <li>Mot de passe oublié</li>
                        <li>Changer son mot de passe</li>
                    </ul>
                </li>
            </ul>

            <p><strong>Ce que je prévois de faire :</strong></p>
            <ul>
                <li>
                    Publications
                    <ul>
                        <li>Décliner les publication pour les cours</li>
                        <li>Commenter une publication</li>
                    </ul>
                </li>
                <li>
                    User
                    <ul>
                        <li>Gérer les accès connecté/non connecté</li>
                        <li>Ajouter une photo de profil</li>
                        <li>Se connecter / s'inscrire avec Facebook si on le souhaite</li>
                        <li>Administration des users (gestion roles - gestion publications)</li>
                    </ul>
                </li>
                <li>Musiciens / Annuaire / Recherche</li>
                <li>Système de messagerie</li>
                <li>Forum</li>
                <li>Recherche</li>
            </ul>
        </div>
    </div>
</template>

<script>
  export default {
    metaInfo: {
      title: 'Liste des choses restant à faire sur MusicAll'
    }
  }
</script>