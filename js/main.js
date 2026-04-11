$(document).ready(function () {

    // --- Dynamic copyright year ---
    $('#currentYear').text(new Date().getFullYear());

    // --- CSRF Token ---
    function generateCSRF() {
        var arr = new Uint8Array(32);
        window.crypto.getRandomValues(arr);
        return Array.from(arr, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    }
    var csrfToken = generateCSRF();
    $('#csrfToken').val(csrfToken);
    // Store in sessionStorage for server-side validation
    sessionStorage.setItem('csrf_token', csrfToken);

    // --- Sticky Header ---
    $(window).on('scroll', function () {
        if ($(this).scrollTop() > 50) {
            $('#header').addClass('scrolled');
        } else {
            $('#header').removeClass('scrolled');
        }
    });

    // --- Burger Menu ---
    $('#burger').on('click', function () {
        $(this).toggleClass('active');
        $('#nav').toggleClass('open');
    });

    // Close mobile nav on link click
    $('.nav__link, .nav__order-btn').on('click', function () {
        $('#burger').removeClass('active');
        $('#nav').removeClass('open');
    });

    // --- Smooth Scroll + Product pre-select ---
    $('a[href^="#"]').on('click', function (e) {
        var product = $(this).data('product');
        if (product) {
            var $firstSelect = $('#orderItems .order__item:first-child select');
            $firstSelect.val(product).trigger('change');
        }
        var target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: target.offset().top - 70
            }, 600);
        }
    });

    // --- Scroll-triggered fade-in ---
    function checkFadeIn() {
        $('.fade-in').each(function () {
            var elTop = $(this).offset().top;
            var winBottom = $(window).scrollTop() + $(window).height();
            if (elTop < winBottom - 60) {
                $(this).addClass('visible');
            }
        });
    }
    $(window).on('scroll', checkFadeIn);
    checkFadeIn(); // Check on load

    // --- Lightbox Gallery ---
    var $lightbox = $('#lightbox');
    var $lightboxImg = $lightbox.find('.lightbox__img');
    var galleryImages = [];
    var currentIndex = 0;

    $('.gallery__item').each(function (i) {
        galleryImages.push($(this).attr('href'));
    });

    $('.gallery__item').on('click', function (e) {
        e.preventDefault();
        currentIndex = $('.gallery__item').index(this);
        openLightbox(galleryImages[currentIndex]);
    });

    function openLightbox(src) {
        $lightboxImg.attr('src', src);
        $lightbox.addClass('active');
        $('body').css('overflow', 'hidden');
    }

    function closeLightbox() {
        $lightbox.removeClass('active');
        $('body').css('overflow', '');
    }

    $lightbox.find('.lightbox__close').on('click', closeLightbox);

    $lightbox.on('click', function (e) {
        if ($(e.target).is($lightbox)) closeLightbox();
    });

    $lightbox.find('.lightbox__prev').on('click', function () {
        currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length;
        $lightboxImg.attr('src', galleryImages[currentIndex]);
    });

    $lightbox.find('.lightbox__next').on('click', function () {
        currentIndex = (currentIndex + 1) % galleryImages.length;
        $lightboxImg.attr('src', galleryImages[currentIndex]);
    });

    $(document).on('keydown', function (e) {
        if (!$lightbox.hasClass('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') $lightbox.find('.lightbox__prev').click();
        if (e.key === 'ArrowRight') $lightbox.find('.lightbox__next').click();
    });

    // --- Order Items Repeater ---
    var _P = window.PRICES || { eggs: 50, incubation: 5, quails: 150, meat: 250 };
    var PRODUCTS = {
        eggs:       { label: 'Перепелині Яйця',  price: _P.eggs,       qtyLabel: 'лотки: по 20 яєць', unit: 'лоток', unitMany: 'лотків' },
        incubation: { label: 'Інкубаційні Яйця', price: _P.incubation, qtyLabel: 'штук',               unit: 'шт',    unitMany: 'шт'    },
        quails:     { label: 'Живі Перепілки',   price: _P.quails,     qtyLabel: 'птиць',               unit: 'птицю', unitMany: 'птиць' },
        meat:       { label: 'М\'ясо Перепілки', price: _P.meat,       qtyLabel: 'кілограми',           unit: 'кг',    unitMany: 'кг'    }
    };

    var itemCounter = 0;

    function makeItemRow(index, preselect) {
        var opts = '<option value="">Оберіть продукт</option>'
            + '<option value="eggs"'       + (preselect === 'eggs'       ? ' selected' : '') + '>Перепелині Яйця ('       + _P.eggs       + 'грн/лоток)</option>'
            + '<option value="incubation"' + (preselect === 'incubation' ? ' selected' : '') + '>Інкубаційні Яйця ('      + _P.incubation + 'грн/шт)</option>'
            + '<option value="quails"'     + (preselect === 'quails'     ? ' selected' : '') + '>Живі Перепілки ('        + _P.quails     + 'грн/птицю)</option>'
            + '<option value="meat"'       + (preselect === 'meat'       ? ' selected' : '') + '>М\'ясо Перепілки ('      + _P.meat       + 'грн/кг)</option>';

        return '<div class="order__item" data-index="' + index + '">'
            + '<div class="form-group">'
            +   '<label>Продукт</label>'
            +   '<select name="items[' + index + '][product]">' + opts + '</select>'
            + '</div>'
            + '<div class="form-group">'
            +   '<label class="qty-label">Кількість</label>'
            +   '<input type="number" name="items[' + index + '][qty]" min="1" placeholder="Кількість">'
            + '</div>'
            + '<button type="button" class="item__remove" title="Видалити"><i class="fas fa-times"></i></button>'
            + '</div>';
    }

    function addItemRow(preselect) {
        $('#orderItems').append(makeItemRow(itemCounter++, preselect));
        updateRemoveButtons();
        if (preselect) {
            updateRowHint($('#orderItems .order__item:last-child'));
        }
        updateAddItemBtn();
    }

    function updateRemoveButtons() {
        var count = $('#orderItems .order__item').length;
        $('#orderItems .item__remove').toggle(count > 1);
    }

    function updateAddItemBtn() {
        var allFilled = true;
        $('#orderItems .order__item').each(function () {
            var product = $(this).find('select').val();
            var qty     = parseInt($(this).find('input[type="number"]').val(), 10);
            if (!product || !qty || qty < 1) { allFilled = false; return false; }
        });
        $('#addItem').prop('disabled', !allFilled);
    }

    function updateRowHint($row) {
        var product = $row.find('select').val();
        var $label  = $row.find('.qty-label');
        var p       = PRODUCTS[product];
        $label.text(p ? 'Кількість (' + p.qtyLabel + ')' : 'Кількість');
    }

    function recalculateSummary() {
        var lines = [];
        var total = 0;

        $('#orderItems .order__item').each(function () {
            var product = $(this).find('select').val();
            var qty     = parseInt($(this).find('input[type="number"]').val(), 10);
            if (!product || !qty || qty < 1) return;
            var p = PRODUCTS[product];
            if (!p) return;
            var subtotal = qty * p.price;
            total += subtotal;
            var detail;
            if (product === 'eggs') {
                detail = qty + ' ' + (qty === 1 ? p.unit : p.unitMany) + ' × ' + p.price + 'грн (= ' + (qty * 20) + ' яєць)';
            } else {
                detail = qty + ' ' + (qty === 1 ? p.unit : p.unitMany) + ' × ' + p.price + 'грн';
            }
            lines.push('<div class="summary__line"><span><strong>' + p.label + ':</strong> <br>' + detail + '</span><span>' + subtotal + 'грн</span></div>');
        });

        if (lines.length === 0) {
            $('#orderSummary').hide();
            return;
        }

        $('#summaryLines').html(lines.join(''));
        $('#summaryTotal').text(total + 'грн');
        $('#orderSummary').show();
    }

    // Init first row
    addItemRow();

    $('#addItem').on('click', function () {
        addItemRow();
    });

    $(document).on('click', '.item__remove', function () {
        $(this).closest('.order__item').remove();
        updateRemoveButtons();
        recalculateSummary();
        updateAddItemBtn();
    });

    $(document).on('change input', '.order__item select, .order__item input[type="number"]', function () {
        updateRowHint($(this).closest('.order__item'));
        recalculateSummary();
        updateAddItemBtn();
    });

    // --- Phone mask: +38 (0XX) XXX-XX-XX ---
    $('#phone').on('input keydown', function (e) {
        var input = this;
        var digits = input.value.replace(/\D/g, '');

        // Always keep leading 38
        if (digits.startsWith('380')) {
            digits = digits.slice(2);
        } else if (digits.startsWith('38')) {
            digits = digits.slice(2);
        } else if (digits.startsWith('8')) {
            digits = digits.slice(1);
        }
        digits = digits.slice(0, 10);

        var mask = '+38 ';
        if (digits.length > 0) mask += '(' + digits.slice(0, 3);
        if (digits.length >= 3) mask += ') ' + digits.slice(3, 6);
        if (digits.length >= 6) mask += '-' + digits.slice(6, 8);
        if (digits.length >= 8) mask += '-' + digits.slice(8, 10);

        input.value = mask;
    });

    $('#phone').on('focus', function () {
        if (!this.value) this.value = '+38 (0';
    });

    $('#phone').on('blur', function () {
        if (this.value === '+38 (0') this.value = '';
    });

    // --- Form Validation & Submission ---
    var phoneRegex = /^\+38 \(0\d{2}\) \d{3}-\d{2}-\d{2}$/;

    function validateField($field) {
        var val = $field.val().trim();
        var $error = $field.siblings('.form-error');
        var isValid = true;

        if ($field.prop('required') && !val) {
            $error.text('Це поле обов\'язкове');
            $field.addClass('error');
            isValid = false;
        } else if ($field.attr('name') === 'phone' && val && !phoneRegex.test(val)) {
            $error.text('Невірний формат телефону');
            $field.addClass('error');
            isValid = false;
        } else {
            $error.text('');
            $field.removeClass('error');
        }

        return isValid;
    }

    $('#orderForm input, #orderForm select').on('blur change', function () {
        if ($(this).prop('required')) validateField($(this));
    });

    $('#orderForm').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var isValid = true;

        $form.find('input[required], select[required]').each(function () {
            if (!validateField($(this))) isValid = false;
        });

        if (!isValid) return;

        var $btn = $form.find('button[type="submit"]');
        var btnText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Відправка...');

        $.ajax({
            url: 'php/mail.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast('success', response.message || 'Замовлення відправлено! Ми зв\'яжемося з вами найближчим часом.');
                    $form[0].reset();
                    $('#orderItems').empty();
                    itemCounter = 0;
                    addItemRow();
                    $('#orderSummary').hide();
                    csrfToken = generateCSRF();
                    $('#csrfToken').val(csrfToken);
                    sessionStorage.setItem('csrf_token', csrfToken);
                } else {
                    showToast('error', response.message || 'Помилка при відправці. Спробуйте ще раз.');
                }
            },
            error: function () {
                showToast('error', 'Помилка з\'єднання з сервером. Зателефонуйте нам напряму.');
            },
            complete: function () {
                $btn.prop('disabled', false).html(btnText);
            }
        });
    });

    // --- Toast Notification ---
    function showToast(type, message) {
        var $toast = $('#toast');
        var iconClass = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';

        $toast.removeClass('show toast--success toast--error')
            .addClass('toast--' + type);
        $toast.find('.toast__icon').attr('class', 'toast__icon ' + iconClass);
        $toast.find('.toast__message').text(message);
        $toast.addClass('show');

        setTimeout(function () {
            $toast.removeClass('show');
        }, 5000);
    }

});
