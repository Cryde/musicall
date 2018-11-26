import $ from "jquery";
import {getMessageByThreadId, getNewMessageForm, sendMessage} from "./message";
import "../plugins/modal";
import select2 from "select2";
select2($);

exports.init = function () {

  handleWriteNewMessage();

  handleOpenModalWriteNewMessage();

  if($('#message-container').length) {
    getDefaultThreadId();
    handleThreadClick();
    handleSendMessageClick();
    handleHistoryChange();

    scrollToBottom();
  }
};

function initTooltip() {
  new Tippy('.message-body', {
    arrow: true
  });
}

function handleWriteNewMessage() {
  $('body').on('submit', '#write-new-message', function (e) {
    let $thisForm = $(this),
      userId = $('#user', $thisForm).val().trim(),
      message = $('#message', $thisForm).val().trim();
    if (userId === '' || message === '') {
      e.preventDefault();
    }
  });
}

function handleOpenModalWriteNewMessage() {
  $('.write-new-message').click(function (e) {
    e.preventDefault();

    let $modalContainer = $('.new-message-modal'),
      userIdSelected = $(this).data('user-id');


    $modalContainer.remove();

    getNewMessageForm({userIdSelected})
      .then((response) => {
        let form = response.form;
        $('body').append(form);

        $('.new-message-modal').modal({closeText: ''});

        enableSelect2ForUsernameInput();
      });

  });
}

function enableSelect2ForUsernameInput() {

  $("#user", '.new-message-modal').select2({
    ajax: {
      url: "/ajax/?controller=message&action=search_user",
      dataType: 'json',
      delay: 250,
      type: "post",
      data: function (params) {
        return {
          q: params.term, // search term
        };
      },
      processResults: function (data) {
        return {
          results: data.items,
        };
      },
      cache: true
    },
    placeholder: "À: ",
    escapeMarkup: function (markup) {
      return markup;
    }, // let our custom formatter work
    minimumInputLength: 3,
    templateResult: formatRepo, // omitted for brevity, see the source of this page
    templateSelection: formatRepoSelection // omitted for brevity, see the source of this page
  });
}

function formatRepo(user) {
  if (user.photo) {

    return `
    <div class='select2-result-user'>
        <div class='select2-result-user-avatar'><img src='${user.photo}' /></div>
          <div class='select2-result-user-login'>
            ${user.pseudo}
          </div>
    </div>
`;
  }
  return user.text;
}

function formatRepoSelection(user) {
  return user.pseudo || user.text;
}


function handleSendMessageClick() {
  $('.send-message').click(function (e) {
    e.preventDefault();

    let threadId = getCurrentThreadId(),
      messageStr = $('#message').val(),
      isScrollBarOnBottom_ = isScrollBarOnBottom();

    sendMessage(threadId, messageStr)
      .then(addMessageToThread)
      .then(updateThread)
      .then(scrollToBottom(isScrollBarOnBottom_))
      .then(setThreadInFirstPosition($('.thread.current')))
      .then(function () {
        $('#message').css('height', '').val('');
      })
      .then(initTooltip);
  });
}

function setThreadInFirstPosition($thread) {
  $thread.prependTo($('#thread-list'));
}

/**
 * Will select the last thread ID or the open one (via url)
 */
function getDefaultThreadId() {
  let lastOpenThread = getLastThreadId();

  if (lastOpenThread) {
    const $thread = $('.thread[data-thread-id=' + lastOpenThread + ']');
    open($thread, lastOpenThread);
  }
}

/**
 *
 */
function handleThreadClick() {
  $('#thread-list').find('.thread').click(function () {
    const threadId = $(this).data('thread-id');
    open($(this), threadId);
  });
}

function open($elem, threadId) {
  changeGlobalMessageCount($elem);
  removeNewState($elem);
  selectCurrentThread($elem);
  changeUrl(threadId);
  openThread(threadId);
}

function selectCurrentThread($thread) {
  $('.thread.current').removeClass('current');
  $thread.addClass('current');
}

function removeNewState($thread) {
  $thread.removeClass('new');
}

function changeGlobalMessageCount($elem) {
  if (!$elem.hasClass('new')) {
    return;
  }

  let $countMessage = $('.message-link').find('span');

  if ($countMessage.length) {
    let nbMessage = +$countMessage.text();

    if (nbMessage > 1) {
      $countMessage.text(nbMessage - 1);
    } else {
      $countMessage.remove();
    }
  }
}

function changeUrl(threadId) {
  window.history.pushState({threadId: threadId}, 'Message', '/message/#' + threadId);
}

function openThread(threadId) {
  setCurrentThreadId(threadId);

  getMessageByThreadId(threadId)
    .then(displayMessageFromThread)
    .then(scrollToBottom(true))
    .then(initTooltip)
    .then(trackScrollBody);
}

function trackScrollBody() {
  $(window).scroll(function () {
    initTooltip();
  })
}

/**
 *
 * @param response
 */
function displayMessageFromThread(response) {
  $('#message-container').html(response);
}

function addMessageToThread(response) {
  $('#message-container').append(response.message);

  return response;
}

function updateThread(response) {
  $('.thread.current')
    .find('.date-message')
    .text(response.metadata.date)
    .end()
    .find('.last-message')
    .html(response.metadata.cleanedMessage);
}

function setCurrentThreadId(threadId) {
  $('#current-thread-id').val(threadId);
}

function getLastThreadId() {
  let threadId = parseInt(window.location.hash.substr(1), 10);
  if (!threadId) {
    threadId = $('#last-open-thread-id').val();
  }

  if (!threadId) {
    threadId = null; // we force the null val
  }

  return threadId;
}

function getCurrentThreadId() {

  let threadId = window.location.hash.substr(1);

  if (!threadId) {
    threadId = $('#current-thread-id').val();
  }

  return threadId;
}

function handleHistoryChange() {
  window.addEventListener('popstate', function (e) {
    openThread(e.state.threadId);
  });
}

function scrollToBottom(isScrollBarOnBottom) {
  return function () {
    if (isScrollBarOnBottom) {
      let jsMessageContainer = $('#message-container').get(0);

      jsMessageContainer.scrollTop = jsMessageContainer.scrollHeight;
    }
  };
}

function isScrollBarOnBottom() {
  let $messageContainer = $('#message-container'),
    jsMessageContainer = $messageContainer.get(0);
  let messageContainerHeight = Math.ceil($messageContainer.outerHeight());
  return jsMessageContainer.scrollHeight - jsMessageContainer.scrollTop === messageContainerHeight;
}