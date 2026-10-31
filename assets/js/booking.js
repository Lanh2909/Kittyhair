/* Kitty Hair – đặt lịch nhiều bước */
(function ($) {
    'use strict';

    var TOTAL_STEPS = 4;
    var step = 1;
    var days = [];
    var $form = $('#bookingForm');
    var old = window.BOOKING_OLD || {};

    function formatMoney(value) {
        return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + 'đ';
    }

    function formatDuration(minutes) {
        if (minutes < 60) { return minutes + ' phút'; }
        var h = Math.floor(minutes / 60), m = minutes % 60;
        return h + ' giờ' + (m ? ' ' + m + ' phút' : '');
    }

    function selectedServices() {
        return $form.find('input[name="services[]"]:checked').map(function () {
            return { id: this.value, name: $(this).data('name'), price: Number($(this).data('price')), duration: Number($(this).data('duration')) };
        }).get();
    }

    function totals() {
        var list = selectedServices();
        return {
            count: list.length,
            price: list.reduce(function (s, x) { return s + x.price; }, 0),
            duration: list.reduce(function (s, x) { return s + x.duration; }, 0)
        };
    }

    function updateTotalsBar() {
        var t = totals();
        $('#totalsBar').html(t.count
            ? t.count + ' dịch vụ · ' + formatDuration(t.duration) + ' · <strong>' + formatMoney(t.price) + '</strong>'
            : 'Chưa chọn dịch vụ');
    }

    function showError(message) {
        var $box = $('#stepError');
        if (!message) { $box.addClass('d-none'); return; }
        $box.text(message).removeClass('d-none');
        $('html, body').animate({ scrollTop: $box.offset().top - 100 }, 200);
    }

    function goTo(n) {
        step = n;
        showError('');
        $('.booking-step').removeClass('active').filter('[data-step="' + n + '"]').addClass('active');
        $('#stepper li').each(function (i) {
            $(this).toggleClass('active', i + 1 === n).toggleClass('done', i + 1 < n);
        });
        $('#btnPrev').prop('hidden', n === 1);
        $('#btnNext').prop('hidden', n === TOTAL_STEPS);
        $('#btnSubmit').prop('hidden', n !== TOTAL_STEPS);
        $('html, body').animate({ scrollTop: $('.page-title').offset().top - 90 }, 150);
    }

    /* ---------- Chọn dịch vụ / stylist ---------- */
    $form.on('change', '.choice-card input', function () {
        $(this).closest('.choice-card').toggleClass('selected', this.checked);
        updateTotalsBar();
        resetSlot();
    });
    $form.on('change', '.stylist-card input', function () {
        $('.stylist-card').removeClass('selected');
        $(this).closest('.stylist-card').addClass('selected');
        resetSlot();
    });

    function resetSlot() {
        $('#inputDate').val('');
        $('#inputTime').val('');
        days = [];
    }

    /* ---------- Lịch trống ---------- */
    function loadSlots() {
        var params = $.param({
            employee_id: $form.find('input[name="employee_id"]:checked').val(),
            services: selectedServices().map(function (s) { return s.id; })
        });
        $('#durationLabel').text(formatDuration(totals().duration));
        $('#slotsLoading').removeClass('d-none');
        $('#slotsError, #slotsEmpty').addClass('d-none');
        $('#dayStrip, #slotGrid').empty();

        return $.getJSON(window.KH_BASE + 'ajax/slots.php?' + params).done(function (res) {
            days = res.days;
            renderDays();
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Không thể tải lịch trống, vui lòng thử lại.';
            $('#slotsError').text(message).removeClass('d-none');
        }).always(function () {
            $('#slotsLoading').addClass('d-none');
        });
    }

    function renderDays() {
        var $strip = $('#dayStrip').empty();
        days.forEach(function (day, index) {
            var count = day.slots.length;
            var $btn = $('<button type="button" class="day-btn"></button>')
                .data('index', index)
                .prop('disabled', count === 0)
                .append($('<small></small>').text(day.weekday))
                .append($('<strong></strong>').text(day.label))
                .append($('<span class="count"></span>').text(count ? count + ' giờ trống' : 'Hết chỗ'));
            if (day.date === $('#inputDate').val()) { $btn.addClass('selected'); }
            $strip.append($btn);
        });
        var current = $('#inputDate').val();
        var index = days.findIndex(function (d) { return d.date === current; });
        if (index < 0) { index = days.findIndex(function (d) { return d.slots.length > 0; }); }
        if (index >= 0) { selectDay(index, current ? $('#inputTime').val() : ''); }
        else { $('#slotsEmpty').removeClass('d-none'); }
    }

    function selectDay(index, keepTime) {
        var day = days[index];
        $('#dayStrip .day-btn').removeClass('selected').eq(index).addClass('selected');
        $('#inputDate').val(day.date);
        var $grid = $('#slotGrid').empty();
        var found = false;
        day.slots.forEach(function (slot) {
            var $b = $('<button type="button" class="slot-btn"></button>').text(slot.start).data('slot', slot);
            if (keepTime && slot.start === keepTime) { $b.addClass('selected'); found = true; }
            $grid.append($b);
        });
        if (!found) { $('#inputTime').val(''); }
    }

    $('#dayStrip').on('click', '.day-btn:not(:disabled)', function () {
        $('#inputTime').val('');
        selectDay($(this).data('index'), '');
    });
    $('#slotGrid').on('click', '.slot-btn', function () {
        $('#slotGrid .slot-btn').removeClass('selected');
        $(this).addClass('selected');
        $('#inputTime').val($(this).data('slot').start);
        showError('');
    });

    /* ---------- Tóm tắt ---------- */
    function renderSummary() {
        var list = selectedServices(), t = totals();
        var date = $('#inputDate').val(), time = $('#inputTime').val();
        var slot = null;
        days.forEach(function (d) { if (d.date === date) { d.slots.forEach(function (s) { if (s.start === time) { slot = s; } }); } });
        var $ul = $('#summaryList').empty();
        list.forEach(function (s) {
            $ul.append($('<li></li>').append($('<span></span>').text(s.name), $('<span></span>').text(formatMoney(s.price))));
        });
        $ul.append($('<li></li>').append('<span>Stylist</span>', $('<strong></strong>').text($form.find('input[name="employee_id"]:checked').data('name'))));
        var parts = date.split('-');
        $ul.append($('<li></li>').append('<span>Thời gian</span>', $('<strong></strong>').text(time + (slot ? ' – ' + slot.end : '') + ' · ' + parts[2] + '/' + parts[1] + '/' + parts[0])));
        $ul.append($('<li></li>').append('<span>Thời lượng</span>', $('<span></span>').text(formatDuration(t.duration))));
        $ul.append($('<li class="total"></li>').append('<span>Tổng cộng</span>', $('<span></span>').text(formatMoney(t.price))));
    }

    /* ---------- Điều hướng ---------- */
    $('#btnPrev').on('click', function () { goTo(step - 1); });

    $('#btnNext').on('click', function () {
        if (step === 1) {
            if (!totals().count) { return showError('Vui lòng chọn ít nhất một dịch vụ.'); }
            goTo(2);
        } else if (step === 2) {
            if (!$form.find('input[name="employee_id"]:checked').length) { return showError('Vui lòng chọn stylist.'); }
            goTo(3);
            loadSlots();
        } else if (step === 3) {
            if (!$('#inputDate').val() || !$('#inputTime').val()) { return showError('Vui lòng chọn ngày và giờ hẹn.'); }
            renderSummary();
            goTo(4);
        }
    });

    function validateContact() {
        var name = $.trim($('#full_name').val());
        var phone = $.trim($('#phone').val());
        var email = $.trim($('#email').val());
        if (name.length < 2) { return 'Vui lòng nhập họ và tên.'; }
        // Định dạng giống server: tiền tố 0 | 84 | +84 | (84) | (+84) + đầu số 3/5/7/8/9 + 8 chữ số
        if (!/^(?:0|84|\+84|\(84\)|\(\+84\))[ .\-]?[35789](?:[ .\-]?\d){8}$/.test(phone)) { return 'Số điện thoại không hợp lệ. Ví dụ: 0912 345 678 hoặc (+84) 912345678.'; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { return 'Email không hợp lệ.'; }
        return '';
    }

    $form.on('submit', function (event) {
        if (!$('#inputDate').val() || !$('#inputTime').val() || !totals().count) {
            event.preventDefault();
            showError('Vui lòng hoàn tất các bước chọn dịch vụ, stylist và thời gian.');
            return;
        }
        var contactError = validateContact();
        if (contactError) {
            event.preventDefault();
            showError(contactError);
            return;
        }
        $('#btnSubmit').prop('disabled', true).text('Đang xử lý…');
    });

    /* ---------- Khôi phục lựa chọn khi server báo lỗi ---------- */
    if (old.services && old.services.length && old.employee_id) {
        old.services.forEach(function (id) {
            $form.find('input[name="services[]"][value="' + id + '"]').prop('checked', true).trigger('change');
        });
        $form.find('input[name="employee_id"][value="' + old.employee_id + '"]').prop('checked', true).trigger('change');
        $('#inputDate').val(old.date || '');
        $('#inputTime').val(old.time || '');
        goTo(3);
        loadSlots().done(function () {
            // Lỗi ở thông tin liên hệ: quay lại thẳng bước 4 nếu khung giờ vẫn còn trống
            if (Number(old.step) === 4 && $('#inputDate').val() && $('#inputTime').val()) {
                renderSummary();
                goTo(4);
            }
        });
    }
    else if (old.services && old.services.length) {
        // Chọn sẵn dịch vụ (vd: bấm "Đặt ngay" từ Bảng giá) – ở lại bước 1
        old.services.forEach(function (id) {
            $form.find('input[name="services[]"][value="' + id + '"]').prop('checked', true).trigger('change');
        });
    }
    updateTotalsBar();
})(jQuery);
