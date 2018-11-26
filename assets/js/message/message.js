const $ = require("jquery");

export {getMessageByThreadId, sendMessage, getNewMessageForm};

/**
 *
 * @param threadId
 * @returns {*}
 */
function getMessageByThreadId(threadId) {
  const deferred = $.Deferred(),
    xhr = $.ajax({
      url: '/ajax/?controller=message&action=get_messages',
      data: {threadId: threadId},
      type: 'GET'
    });

  xhr
    .done(function (response) {
      if (response.success) {
        deferred.resolve(response.content);
      } else {
        deferred.reject(response.errors);
      }
    });

  return deferred.promise();
}

function sendMessage(threadId, message) {
  const deferred = $.Deferred(),
    xhr = $.ajax({
      url: '/ajax/?controller=message&action=send_message',
      data: {threadId: threadId, message: message},
      type: 'POST'
    });

  xhr
    .done(function (response) {
      if (response.success) {
        deferred.resolve(response);
      } else {
        deferred.reject(response.errors);
      }
    });

  return deferred.promise();
}

function getNewMessageForm(data) {
  const deferred = $.Deferred(),
    xhr = $.ajax({
      url: '/ajax/?controller=message&action=write-new-message',
      type: 'POST',
      data: data
    });

  xhr
    .done(function (response) {
      if (response.success) {
        deferred.resolve(response);
      } else {
        deferred.reject(response.errors);
      }
    });

  return deferred.promise();
}