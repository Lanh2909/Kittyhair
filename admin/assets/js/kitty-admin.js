/* Kitty Hair – JavaScript trang quản trị */
(function ($) {
    'use strict';

    /* Gán mã lịch hẹn vào modal hủy */
    $('#cancelModal').on('show.bs.modal', function (event) {
        $('#cancelAppointmentId').val($(event.relatedTarget).data('id'));
        $('#cancelReason').val('');
    });

    /* Xác nhận trước khi gửi form xóa */
    $(document).on('submit', 'form[data-confirm]', function (event) {
        if (!window.confirm($(this).data('confirm'))) {
            event.preventDefault();
        }
    });

    /* Xem trước ảnh trước khi tải lên */
    $(document).on('change', 'input[type="file"][data-preview]', function () {
        var file = this.files && this.files[0];
        var $target = $($(this).data('preview'));
        if (!file) { $target.attr('hidden', true); return; }
        $target.attr('src', URL.createObjectURL(file)).removeAttr('hidden');
    });

    /* Lịch làm việc: bật/tắt giờ theo từng ngày */
    $('.day-switch').on('change', function () {
        $(this).closest('.worktime-day').find('.time-range').toggle(this.checked);
    });
})(jQuery);

/* Modal thêm/sửa danh mục dịch vụ */
(function ($) {
    'use strict';
    $('#categoryModal').on('show.bs.modal', function (event) {
        var $btn = $(event.relatedTarget);
        var isEdit = String($btn.data('id')) !== '';
        $('#categoryId').val($btn.data('id'));
        $('#categoryName').val($btn.data('name'));
        $('#categoryModalTitle').text(isEdit ? 'Sửa danh mục' : 'Thêm danh mục');
    });
})(jQuery);

/* Modal đặt lại mật khẩu tài khoản quản trị */
(function ($) {
    'use strict';
    $('#resetModal').on('show.bs.modal', function (event) {
        var $btn = $(event.relatedTarget);
        $('#resetAdminId').val($btn.data('id'));
        $('#resetAdminName').text($btn.data('name'));
        $('#resetPassword, #resetConfirm').val('').attr('type', 'password');
    });

    /* Tạo mật khẩu ngẫu nhiên (12 ký tự, có cả chữ và số) và hiển thị để quản trị viên chuyển cho người dùng */
    function randomPassword() {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        var password;
        do {
            var values = new Uint32Array(12);
            window.crypto.getRandomValues(values);
            password = Array.prototype.map.call(values, function (v) { return chars.charAt(v % chars.length); }).join('');
        } while (!/[A-Za-z]/.test(password) || !/\d/.test(password));
        return password;
    }
    $('#btnGeneratePassword').on('click', function () {
        var password = randomPassword();
        $('#resetPassword, #resetConfirm').val(password).attr('type', 'text');
    });
})(jQuery);
