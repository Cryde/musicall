import $ from "jquery";

/** Selecteur Css qui déclenche l'evenmenet de sauvegarde du message */
const postWallMessageSelector = '.postWallMessage',
  postWallButtonSelector = '.postWallButtonMessage',
  wallContainer = '.messagesWallContainer',
  // Selecteur pour la suppresion d'un message sur le mur
  deleteWallMessageSelector = '.deleteWallPost'
;

/* Fonction permettant d'attraper l'evenement de l'envois d'un message
 * sur le mur d'un membre */
function catchPostWallMessage() {
  $(postWallButtonSelector).on('click', function (e) {
    const wallMessage = $.trim($(postWallMessageSelector).val()),
      destinationUrl = '/ajax/?controller=profil&action=add_message_wall',
      memberId = $(this).data('member-id');
    if (wallMessage !== '') {
      postWallMessage(destinationUrl, memberId, wallMessage);
    }
    e.preventDefault();
  });
}

function postWallMessage(destinationUrl, memberId, wallMessage) {
  $.ajax({
    url: destinationUrl,
    type: 'POST',
    data: {member_id: memberId, message: wallMessage}
  })
    .done(postWallMessageSuccess)
    .fail(postWallMessagFail);
}

function postWallMessageSuccess(response) {
  $(postWallMessageSelector).val('');
  const htmlElem = $(response).hide();
  $(wallContainer).prepend(htmlElem);
  htmlElem.fadeIn('slow');
}

//TODO
function postWallMessagFail() {
}

/** */
function catchDeleteWallMessage() {
  $(wallContainer).on('click', deleteWallMessageSelector, function (e) {
    e.preventDefault();
    if (confirm('Êtes vous sur de vouloir supprimer ce message ?')) {
      const destinationUrl = '/ajax/?controller=profil&action=delete_message_wall',
        messageId = $(this).data('post-id');
      deleteWallMessage(destinationUrl, messageId);
    }
  });
}

function deleteWallMessage(destinationUrl, messageId) {
  $.ajax({
    url: destinationUrl,
    type: 'POST',
    data: {message_id: messageId}
  })
    .done(deleteWallMessageSuccess)
    .fail();
}

function deleteWallMessageSuccess(response) {
  $('.id-message-' + response).fadeOut(function () {
    $(this).remove();
  });
}

// Partie publique
exports.init = function () {
  catchPostWallMessage();
  catchDeleteWallMessage();
};

