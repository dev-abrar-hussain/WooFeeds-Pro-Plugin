(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.woofeeds-copy');
    if (!button) return;

    var value = button.getAttribute('data-copy');
    navigator.clipboard.writeText(value).then(function () {
      var original = button.textContent;
      button.textContent = wooFeedsProAdmin.copied;
      window.setTimeout(function () { button.textContent = original; }, 1600);
    }).catch(function () {
      window.prompt(wooFeedsProAdmin.copyFailed, value);
    });
  });
}());

