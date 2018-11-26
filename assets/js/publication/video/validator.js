import $ from "jquery";

export {isValid};

/**
 *
 * @param publication
 * @returns {*}
 */
function isValid(publication) {
  const deferred = $.Deferred(),
    xhr = $.ajax({
      url: '/ajax/?controller=publication&action=validation',
      data: publication,
      type: 'post'
    });

  xhr
    .done(function (response) {
      if (response.success) {
        deferred.resolve(response.success);
      } else {
        deferred.reject(response.errors);
      }
    });

  return deferred.promise();
}