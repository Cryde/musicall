import $ from "jquery";

export {init, stop};

function init() {
  let $buttonLoading = $('.button-loading');

  if ($buttonLoading.length) {

    $buttonLoading.on('click', function () {

      let $this = $(this),
        $iItem = $this.find('i');

      if ($iItem.length) {
        $this.data('new-i', false);
        $iItem.data('prevClass', $iItem.attr('class'));
        $iItem.removeClass().addClass('fa fa-spin fa-cog');
      } else {
        $this.prepend('<i class="fa fa-cog fa-spin" /> ');
        $this.data('new-i', false);
      }
      $this.data('is-working', true);
    });

    $buttonLoading.on('stop:working', function () {
      let $this = $(this),
        $iItem = $this.find('i'),
        isWorking = $this.data('is-working'),
        isNewI = $this.data('new-i');

      if (isWorking) {
        if (isNewI) {
          $iItem.remove();
        } else {
          $iItem.removeClass('fa-cog fa-spin').addClass($iItem.data('prevClass'));
        }
      }
    });
  }
}

function stop() {
  let $buttonLoading = $('.button-loading');

  $buttonLoading.trigger('stop:working');
}
