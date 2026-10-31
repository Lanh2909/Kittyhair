/* Kitty Hair – JavaScript giao diện khách hàng */
(function ($) {
    'use strict';

    /* ---------- Form liên hệ (AJAX) ---------- */
    $('#contactForm').on('submit', function (event) {
        event.preventDefault();
        var $form = $(this);
        var $status = $('#contactStatus');
        var $button = $form.find('button[type="submit"]');

        $button.prop('disabled', true);
        $.ajax({
            url: window.KH_BASE + 'ajax/contact.php',
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        }).done(function (res) {
            $status.html('<div class="alert alert-success mb-0"></div>').children().text(res.message);
            $form[0].reset();
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Không thể gửi tin nhắn, vui lòng thử lại.';
            $status.html('<div class="alert alert-danger mb-0"></div>').children().text(message);
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    /* ---------- Xem trước ảnh trước khi tải lên ---------- */
    $(document).on('change', 'input[type="file"][data-preview]', function () {
        var file = this.files && this.files[0];
        var $target = $($(this).data('preview'));
        if (!file) {
            $target.attr('hidden', true);
            return;
        }
        $target.attr('src', URL.createObjectURL(file)).removeAttr('hidden');
    });

    /* ---------- Xác nhận trước khi gửi form nguy hiểm ---------- */
    $(document).on('submit', 'form[data-confirm]', function (event) {
        if (!window.confirm($(this).data('confirm'))) {
            event.preventDefault();
        }
    });
})(jQuery);
