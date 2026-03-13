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
    $('.nav__link').on('click', function () {
        $('#burger').removeClass('active');
        $('#nav').removeClass('open');
    });

    // --- Smooth Scroll ---
    $('a[href^="#"]').on('click', function (e) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: target.offset().top - 70
            }, 600);
        }
    });

    // --- Product buttons → pre-select product type ---
    $('[data-product]').on('click', function () {
        var product = $(this).data('product');
        if (product === 'eggs') {
            $('#product_type').val('eggs');
        } else if (product === 'quails') {
            $('#product_type').val('quails');
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

    // --- Form Validation & Submission ---
    var phoneRegex = /^\+?3?8?\s?\(?\d{3}\)?\s?\d{3}[\s-]?\d{2}[\s-]?\d{2}$/;

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
        validateField($(this));
    });

    $('#orderForm').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var isValid = true;

        $form.find('input[required], select[required]').each(function () {
            if (!validateField($(this))) {
                isValid = false;
            }
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
                    // Regenerate CSRF token
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
