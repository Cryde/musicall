import $ from "jquery";
import {avoidFormSubmit, displayErrors, displayNotification, getDataForm, stopButtonLoading} from "./utils";

export default init;

const $publicationForm = $('#publication-text'),
  $saveDraft = $publicationForm.find('#saveDraft');

function init() {
  if ($saveDraft.length) {
    handleSaveDraft();
  }
}


function handleSaveDraft() {

  avoidFormSubmit($publicationForm);

  $saveDraft.on('click', function (e) {
    e.preventDefault();
    const dataForm = getDataForm($publicationForm);

    saveForm(dataForm)
      .then(displayNotification)
      .then(emptyFileInput)
      .then(stopButtonLoading)
      .catch(displayErrors);
  });
}


/**
 * We empty the input file when file has been uploaded
 */
function emptyFileInput() {
  $('#icon-publication').val('');
}


function saveForm(dataForm) {

  const deferred = $.Deferred(),
    xhr = $.ajax({
      url: '/ajax/?controller=publication&action=text-save',
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
