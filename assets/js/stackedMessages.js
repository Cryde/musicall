const $ = require('jquery');

/** Selecteur Css qui déclenche l'evenmenet de sauvegarde du message */
const stackedMessageContainer = '.global-alert-container';

function hasMessage() {
  return $(stackedMessageContainer).length > 0;
}

// Partie publie
exports.init = function () {
  if (hasMessage()) {
    $(stackedMessageContainer)
      .fadeIn(1000, function () {
        setTimeout(function (that) {
          that.fadeOut();
        }, 5000, $(this));
      });
  }
};
