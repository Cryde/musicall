import $ from "jquery";
import mediumInsert from "medium-editor-insert-plugin";
import MediumEditor from "medium-editor";
//import loader from 'waiting-load';

let editor = null;

export {init, getContent};

function init() {
  initTextEditor();
  initIconPreview();
}

function initTextEditor() {
  editor = new MediumEditor('#editor', {
    buttonLabels: 'fontawesome',
    toolbar: {
      buttons: [
        'bold', 'italic', 'underline', 'anchor', 'h2', 'h3',
        'justifyLeft', 'justifyCenter', 'justifyRight'
      ]
    }
  });
  mediumInsert($);

  $('#editor').mediumInsert({
    editor: editor,
    addons: {
      images: {
        captions: false,
        styles: null,
        fileUploadOptions: {
          url: '/ajax/index.php?controller=publication&action=image-upload',
          acceptFileTypes: /(.|\/)(jpe?g|png)$/i,
          singleFileUploads: true,
          paramName: 'file'
        }
      },
      embeds: { // (object) Embeds addon configuration
        placeholder: 'Copiez une url YouTube ou Vimeo et pressez ensuite "Enter"', // (string) Placeholder displayed when entering URL to embed
        captions: false, // (boolean) Enable captions
        styles: null,
        oembedProxy: '/ajax/?controller=publication&action=get_video_info'
      }
    },
  });
  document.execCommand('styleWithCSS', false, true);
}

/**
 * This is a little dirty...
 * I had to do that because the plugin let its <div class="medium-insert-buttons" /> in the final content
 * @returns {*}
 */
function getContent() {
  const editorHtml = $('#editor').val(),
    contentNoButtons = $(`<div>${editorHtml}</div>`).find('.medium-insert-buttons').remove().end();
  return contentNoButtons.html();
}


function initIconPreview() {
  const $iconPublicationInput = $('#icon-publication');
  $iconPublicationInput.on('change', handleImage);
  $('.dropzone-previews').click(() => {
    $iconPublicationInput.click();
  });
}

function handleImage(e) {

  const reader = new FileReader();

  reader.onload = function (event) {
    const image = new Image();
    image.src = event.target.result;

    image.onload = function () {

      if (this.width === this.height) {
        const $dropzonePreview = $('.dropzone-previews');
        $dropzonePreview.find('img, span').remove();

        const $img = $('<img/>').attr('src', event.target.result);
        $dropzonePreview.append($img);
      } else {
        $('#icon-publication').val('');
        alert('L\'image doit être carrée');
      }
    };

  };
  reader.readAsDataURL(e.target.files[0]);
}