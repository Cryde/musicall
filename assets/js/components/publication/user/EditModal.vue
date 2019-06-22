<template>
    <b-modal id="modal-publication-properties" size="lg" title="Propriété de la publication">

        <div>
            <b-form-group description="Le titre de votre publication">
                <b-form-input v-model="currentTitle" :state="validation.title.state"
                              placeholder="Votre titre ici"></b-form-input>
                <b-form-invalid-feedback :state="validation.title.state">
                    {{ validation.title.message }}
                </b-form-invalid-feedback>
            </b-form-group>

            <b-form-group description="Cette courte description apparaitra sur la page d'accueil">
                <b-form-textarea
                        v-model="currentDescription"
                        id="textarea"
                        placeholder="Une courte description de l'article"
                        rows="3"
                ></b-form-textarea>
            </b-form-group>
        </div>


        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button variant="default" @click="cancel()">
                Annuler
            </b-button>
            <b-button variant="success" @click="save">
                Sauver
            </b-button>
        </template>
    </b-modal>
</template>

<script>
  export default {
    props: ['title', 'description', 'validation'],
    data() {
      return {
        currentTitle: '',
        currentDescription: ''
      }
    },
    mounted(){
      console.log(this.title);
      this.currentTitle = this.title;
      this.currentDescription = this.description;
    },
    methods: {
      save() {
        this.$emit('saveProperties', {title: this.currentTitle, description: this.currentDescription});
      }
    }
  }
</script>