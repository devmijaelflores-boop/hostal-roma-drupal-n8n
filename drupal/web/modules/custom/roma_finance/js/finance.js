(function (Drupal, once) {
  Drupal.behaviors.romaFinancePrint = {
    attach(context) {
      once('roma-finance-print', '.finance-print', context).forEach(button => {
        button.addEventListener('click', () => window.print());
      });
    },
  };
})(Drupal, once);
