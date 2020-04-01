<template>
    <div>
        <h1>Todo</h1>

        <div class="content-box p-lg-3 p-3">
            <p>
                MusicAll est pour l'instant en phase de re-développement.<br/>
                C'est à dire que je reprends tout à partir de zero et reconstruit les briques unes à unes.<br/><br/>

                <strong>Toutes les données que vous rentrerez ici sont suceptible de disparaitre à tout jamais.</strong>
            </p>

            <p>
                <strong>Ce qui est testable (pour l'instant SEULEMENT sur ordinateur) :</strong>
            </p>

            <ul>
                <li>S'inscrire (+recevoir un email de confirmation)</li>
                <li>Se connecter (et se déconnecter)</li>
                <li>Voir la liste des publications (Home / Publications)</li>
                <li>Voir les catégories de publications et les publication associée (Interviews, Live-reports, ...)</li>
                <li>Voir une publication "seule"</li>
                <li>Liste des
                    <router-link :to="{name: 'gallery_list'}">galerie de photos</router-link>
                </li>
                <li>Liste des photos pour une galerie de photo (+ cliquer et naviguer d'une photo à l'autre)</li>
                <li>Quand vous êtes inscrit et connectés :
                    <ul>
                        <li>
                            En cliquant en haut à droite, vous avez accès à un sous menu
                            <ul>
                                <li>Dans "Paramètres", changer son mot de passe</li>
                                <li>Dans "Mes publications" : liste en tableau de toutes les publications/videos que
                                    vous avez posté et en dessous, liste des galeries de photos que vous avez envoyé
                                </li>
                                <li>Dans "Mes publications" : vous avez la possibilité d'ajouter soit une publication,
                                    soit une vidéo soit une galerie de photo
                                </li>
                                <li>Vous pouvez ajouter une vidéo en mettant l'url youtube d'une vidéo d'un artiste par
                                    exemple, toutes les infos de la vidéo apparaitront ensuite en dessous. Une fois
                                    envoyé, la vidéo devrait apparaitre sur la home page et dans votre liste de
                                    publications
                                </li>
                                <li>Vous pouvez ajouter une galerie d'image en lui donnant un titre, ensuite vous
                                    arriverez dans l'édition de la galerie, vous pourrez uploader des images, en
                                    supprimer et définir une image comme photo de "couverture" de la galerie.
                                </li>
                                <li>Vous pouvez ajouter une publication, vous devrez définir un titre et une catégorie,
                                    vous arriverez ensuite à l'éditiion de la publication, vous aurez accès à un éditeur
                                    qui vous permettra de mettre en forme votre publication, et d'y ajouter des
                                    images.<br/>Vous aurez ensuite la possibilité d'enregistrer en "brouillon" votre
                                    publication (pour y revenir plus tard dessus) ou de l'envoyer en validation (voir
                                    point suivant). Si vous
                                    l'envoyez en validation, il faudra attendre que celle ci soit validée par un
                                    administrateur avant d'aparaitre sur le site.
                                </li>
                                <li>Pour envoyer en validation une publication, revenez à votre liste de publication
                                    puis cliquez sur <span class="btn btn-outline-success btn-sm"><i
                                            class="far fa-paper-plane"></i></span></li>
                                <li>Vous avez à tout moment la possibilité de supprimer une publication en "brouillon"
                                </li>
                            </ul>
                        </li>

                    </ul>
                </li>
            </ul>

            <p>
                <strong>Ce qui est fait (+ de détails):</strong>
            </p>
            <ul>
                <li>Possibilité de s'inscrire
                    <ul>
                        <li>Envoie d'un email pour confirmer son compte</li>
                    </ul>
                </li>
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
                    Galerie (Photos)
                    <ul>
                        <li>Création d'une galerie de photo</li>
                        <li>Edition d'une galerie de photo</li>
                        <li>Publication de la galerie</li>
                        <li>Ajout de photo à la galerie (upload)</li>
                        <li>Suppression d'une photo</li>
                        <li>Définition de la photo de couverture</li>
                    </ul>
                </li>
                <li>
                    User
                    <ul>
                        <li>Mot de passe oublié
                            <ul>
                                <li>Envoie d'un email pour changer son mot de passe</li>
                            </ul>
                        </li>
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