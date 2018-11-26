import {getContent as getContentEditor} from "./editor";
import createNotification from "../../alert-notification/create";
import $ from "jquery";
import * as buttonLoading from "../../button-visual-loading";

export {getDataForm, avoidFormSubmit, displayErrors, displayNotification, stopButtonLoading};

function getDataForm($form) {
  const contentEditor = getContentEditor();
  const formData = new FormData($form[0]);

  formData.delete('content');
  formData.append('content', contentEditor);
  return formData;
}


function avoidFormSubmit($form) {
  /* Avoid submit the form directly */
  $form.on('submit', function (e) {
    e.stopImmediatePropagation();
    e.preventDefault();
  });
}


function displayNotification(message) {
  const notification = createNotification({type: 'success', 'message': message});
  const $notification = $(notification);

  $('.alert-container')
    .html($notification);

  $notification.show()
    .fadeOut(2000, function () {
      $(this).empty();
    });
  addClose($notification);
}

function displayErrors(message) {
  stopButtonLoading();
  const notification = createNotification({type: 'error', 'message': message});
  const $notification = $(notification);
  $('.alert-container').html($notification).show();
  addClose($notification);
}

function addClose($notification) {
  $notification.find('.close').on('click', () => {
    $notification.remove();
  });
}

function stopButtonLoading() {
  buttonLoading.stop();
}