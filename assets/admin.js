(function ($) {
  $(function () {
    var sampleVars = {
      "{order_id}": "1001",
      "{customer_name}": "Rohit Demo",
      "{customer_phone}": "919696969696",
      "{order_total}": "INR 499",
      "{site_name}": "Demo Store",
      "{order_status}": "Processing",
      "{order_items}": "Starter Plan x 1, Setup Service x 1",
      "{order_items_count}": "2",
      "{billing_email}": "customer@example.com",
      "{shipping_address}": "Indore, Madhya Pradesh",
      "{payment_method}": "Razorpay"
    };

    function renderPreview(value) {
      Object.keys(sampleVars).forEach(function (key) {
        value = value.split(key).join(sampleVars[key]);
      });
      return value;
    }

    function updateTemplatePreview() {
      var id = $(this).data('preview');
      if (!id) return;
      $('#' + id).text(renderPreview($(this).val()));
    }

    $(document).on('input', '.msgroute-template-input', updateTemplatePreview);
    $('.msgroute-template-input').each(updateTemplatePreview);

    $('#msgroute-send-test').on('click', function (event) {
      event.preventDefault();
      var $button = $(this);
      var $result = $('#msgroute-test-result');
      $button.prop('disabled', true).text('Sending...');
      $result.removeClass('success error').text('');

      $.post(MsgRouteNotifications.ajaxUrl, {
        action: 'msgroute_notifications_send_test',
        nonce: MsgRouteNotifications.nonce,
        phone: $('#msgroute-test-phone').val(),
        message: $('#msgroute-test-message').val()
      }).done(function (response) {
        if (response && response.success) {
          $result.addClass('success').text(response.data.message || 'Message sent successfully.');
          return;
        }
        $result.addClass('error').text((response && response.data && response.data.message) || 'Message failed.');
      }).fail(function (xhr) {
        var message = 'Message failed.';
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          message = xhr.responseJSON.data.message;
        }
        $result.addClass('error').text(message);
      }).always(function () {
        $button.prop('disabled', false).text('Send Test');
      });
    });
  });
})(jQuery);

