<template>
    <nav id="menu">
        <ul>

            <li><router-link :to="{ name: 'home' }">Home</router-link></li>
            <li>
                <router-link :to="{ name: 'publication' }">Publications</router-link>
                <ul>
                    <li class="">
                        <a href="/publications/categorie/chroniques">Chroniques</a>
                    </li>
                    <li class="">
                        <a href="/publications/categorie/live-reports">Live-reports</a>
                    </li>
                    <li class="">
                        <a href="/publications/categorie/interviews">Interviews</a>
                    </li>
                    <li class="">
                        <a href="/publications/categorie/articles">Articles</a>
                    </li>
                    <li class="">
                        <a href="/publications/categorie/news">News</a>
                    </li>
                    <li class=""><a href="/publications/categorie/decouvertes">Découvertes</a></li>
                </ul>
            </li>

            <li>
                <a href="">Cours</a>
                <ul>
                    <li><a href="/cours/guitare">Guitare</a></li>
                    <li><a href="/cours/basse">Basse</a></li>
                    <li><a href="/cours/mao">MAO</a></li>
                    <li><a href="/cours/batterie">Batterie</a></li>
                    <li><a href="/cours/inclassable">Inclassable</a></li>
                </ul>
            </li>
            <li>
                <a href="">Annuaire</a>
            </li>
            <li>
                <a href="">Forum</a>
            </li>

            <li>
                <router-link :to="{ name: 'todo' }">Todo</router-link>
            </li>
        </ul>
    </nav>


</template>