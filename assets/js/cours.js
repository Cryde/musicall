import $ from "jquery";

/**
 Fonction permettant d'avoir une preview d'un contenu avec balise
 Notamment sur le forum, cours, ...
 */
function view(textareaId, viewDiv) {

  const content = $('#' + textareaId).val();
  $.ajax({
    url: "/ajax/?controller=bbcode",
    type: "POST",
    data: 'string=' + content
  })
    .done(function (response) {
      $('#' + viewDiv).html(response);
    });
}

function insertTag(startTag, endTag, textareaId, tagType) {
  const field = document.querySelector('#' + textareaId);
  const scroll = field.scrollTop;
  field.focus();

  let currentSelection, textRange, startSelection, endSelection;
  if (window.ActiveXObject) {
    textRange = document.selection.createRange();
    currentSelection = textRange.text;
  }
  else {
    startSelection = field.value.substring(0, field.selectionStart);
    currentSelection = field.value.substring(field.selectionStart, field.selectionEnd);
    endSelection = field.value.substring(field.selectionEnd);
  }


  if (tagType) {
    switch (tagType) {
      case "lien":
        endTag = "</lien>";
        if (currentSelection) {
          if (currentSelection.indexOf("http://") === 0 || currentSelection.indexOf("https://") === 0 || currentSelection.indexOf("ftp://") === 0 || currentSelection.indexOf("www.") === 0) {
            const label = prompt("Quel est le libellé du lien ?") || "";
            startTag = "<lien url=\"" + currentSelection + "\">";
            currentSelection = label;
          } else {
            const URL = prompt("Quelle est l'url ? (Avec http://)");
            startTag = "<lien url=\"" + URL + "\">";
          }
        } else {
          const URL = prompt("Quelle est l'url ? (Avec http://)") || "";
          const label = prompt("Quel est le libellé du lien ?") || "";
          startTag = "<lien url=\"" + URL + "\">";
          currentSelection = label;
        }
        break;
      case "citation":
        endTag = "</citation>";
        if (currentSelection) {
          if (currentSelection.length > 30) {
            const auteur = prompt("Quel est l'auteur de la citation ?") || "";
            startTag = "<citation nom=\"" + auteur + "\">";
          } else {
            const citation = prompt("Quelle est la citation ?") || "";
            startTag = "<citation nom=\"" + currentSelection + "\">";
            currentSelection = citation;
          }
        } else {
          const auteur = prompt("Quel est l'auteur de la citation ?") || "";
          const citation = prompt("Quelle est la citation ?") || "";
          startTag = "<citation nom=\"" + auteur + "\">";
          currentSelection = citation;
        }
        break;
      case "video":
        endTag = "</video>";

        if (currentSelection) {
          if (currentSelection.length > 30) {
            const titre_video = prompt("Quel est le titre de la vidéo ?") || "";
            startTag = "<video=\"" + titre_video + "\">";
          } else {
            const url_video = prompt("Quelle est l'Url de la vidéo ?") || "";
            startTag = "<video=\"" + currentSelection + "\">";
            currentSelection = url_video;
          }
        } else {
          const titre_video = prompt("Quel est le titre de la vidéo ?") || "";
          const url_video = prompt("Quelle est l'Url de la vidéo ?") || "";
          startTag = "<video=\"" + titre_video + "\">";
          currentSelection = url_video;
        }
        break;
    }
  }

  if (window.ActiveXObject) {
    textRange.text = startTag + currentSelection + endTag;
    textRange.moveStart('character', -endTag.length - currentSelection.length);
    textRange.moveEnd('character', -endTag.length);
    textRange.select();
  } else { // Ce n'est pas IE
    field.value = startSelection + startTag + currentSelection + endTag + endSelection;
    field.focus();
    field.setSelectionRange(startSelection.length + startTag.length, startSelection.length + startTag.length + currentSelection.length);
  }

  field.scrollTop = scroll; // et on redéfinit le scroll
  document.querySelector('#couleur_form').options[0].selected = true;
  document.querySelector('#titre_form').options[0].selected = true;
}

/**
 *
 * @returns {boolean}
 */
function verif_tuto() {
  const titre = document.getElementById("tutoriel").elements["champs_titre"].value.trim();

  const categorie = document.getElementById("tutoriel").elements['categorie'].options[document.getElementById("tutoriel").elements['categorie'].selectedIndex].value.trim();

  let prob_1 = '',
    prob_2 = '',
    ok_1 = false,
    ok_2 = false;

  if (titre !== "") {
    if (titre.length < 6) {
      prob_1 += 'Votre titre doit contenir 6 caractères minimun et doit être explicite\n';
    } else {
      prob_1 = '';
      ok_1 = true;
    }
  } else {
    prob_1 += 'Vous devez mettre un titre à votre tutoriel\n';
  }

  if (categorie === 'Rien' || categorie === '') {
    prob_2 += 'Vous devez choisir une catégorie pour votre tutoriel\n';
  } else {
    ok_2 = true;
  }


  if (ok_1 && ok_2) {
    return true;
  } else {
    alert(prob_1 + '' + prob_2);
    return false;
  }
}


exports.init = function () {

  $('#tutoriel').submit(function () {
    return verif_tuto();
  });


  $('#previewClick').click(function () {
    view("textarea", 'viewDiv');
  });


  $('#couleur_form').change(function () {
    insertTag('<couleur="' + $(this).get(0).options[$(this).get(0).selectedIndex].value + '">', '</couleur>', 'textarea');
  });

  $('#titre_form').change(function () {
    insertTag('<titre="' + $(this).get(0).options[$(this).get(0).selectedIndex].value + '">', '</titre>', 'textarea');
  });

  $('#align-left').click(function () {
    insertTag('<position="text_gauche">', '</position>', 'textarea');
  });
  $('#align-center').click(function () {
    insertTag('<position=""texte_centre">', '</position>', 'textarea');
  });
  $('#align-right').click(function () {
    insertTag('<position="text_droite">', '</position>', 'textarea');
  });

  $('#bold').click(function () {
    insertTag('<gras>', '</gras>', 'textarea');
  });

  $('#italic').click(function () {
    insertTag('<italique>', '</italique>', 'textarea');
  });

  $('#underline').click(function () {
    insertTag('<souligne>', '</souligne>', 'textarea');
  });

  $('#linkinsert').click(function () {
    insertTag('', '', 'textarea', 'lien');
  });

  $('#imageinsert').click(function () {
    insertTag('<image>', '</image>', 'textarea');
  });

  $('#youtubeinsert').click(function () {
    insertTag('', '', 'textarea', 'video');
  });

};
