import $ from "jquery";
import {avoidFormSubmit, displayErrors, displayNotification, getDataForm, stopButtonLoading} from "./utils";

export default init;

const $publicationForm = $('#publication-text'),
  $publish = $publicationForm.find('#publish');

function init() {
  if ($publish.length) {
    handlePublish();
  }
}


function handlePublish() {

  avoidFormSubmit($publicationForm);

  $publish.on('click', function (e) {
    e.preventDefault();
    const dataForm = getDataForm($publicationForm);

    saveForm(dataForm)
      .then(displayNotification)
      .then(stopButtonLoading)
      .catch(displayErrors);
  });
}

function saveForm(dataForm) {

  const deferred = $.Deferred(),
    xhr = $.ajax({
      url: '/ajax/?controller=publication&action=text-publish',
      type: 'POST',
      data: dataForm,
      cache: false,
      contentType: false,
      processData: false
    });

  xhr
    .done(function (response) {
      if (response.success) {
        deferred.resolve(response.messages);
      } else {
        deferred.reject(response.messages);
      }
    });

  return deferred.promise();
}
