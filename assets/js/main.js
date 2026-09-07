/* NUPS-G Church - shared front-end behaviour */
(function () {
  'use strict';

  /* ---------- Toast ---------- */
  function toast(message, icon) {
    var wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      document.body.appendChild(wrap);
    }
    var el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = '<i class="fas ' + (icon || 'fa-check-circle') + '"></i><span></span>';
    el.querySelector('span').textContent = message;
    wrap.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .4s, transform .4s';
      el.style.opacity = '0';
      el.style.transform = 'translateY(12px)';
      setTimeout(function () { el.remove(); }, 400);
    }, 3200);
  }
  window.toast = toast;

  /* ---------- Mobile drawer ---------- */
  var toggle = document.querySelector('.menu-toggle');
  var drawer = document.querySelector('.drawer');
  var overlay = document.querySelector('.overlay');
  var drawerClose = document.querySelector('.drawer-close');

  function setDrawer(open) {
    if (!drawer) return;
    drawer.classList.toggle('open', open);
    if (toggle) toggle.classList.toggle('open', open);
    if (overlay) overlay.classList.toggle('open', open);
    document.body.classList.toggle('no-scroll', open);
  }
  if (toggle && drawer) {
    toggle.addEventListener('click', function () { setDrawer(!drawer.classList.contains('open')); });
  }
  if (drawerClose) drawerClose.addEventListener('click', function () { setDrawer(false); });
  if (overlay) overlay.addEventListener('click', function () { setDrawer(false); });
  drawer && drawer.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () { setDrawer(false); });
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) setDrawer(false);
  });

  /* ---------- Sticky header shadow ---------- */
  var header = document.querySelector('.site-header');
  var toTop = document.querySelector('.to-top');
  function onScroll() {
    var y = window.scrollY || 0;
    if (header) header.classList.toggle('is-scrolled', y > 8);
    if (toTop) toTop.classList.toggle('show', y > 600);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (toTop) {
    toTop.addEventListener('click', function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ---------- User chip dropdown (click/touch friendly) ---------- */
  document.querySelectorAll('.user-chip').forEach(function (chip) {
    var btn = chip.querySelector('button');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      document.querySelectorAll('.user-chip.open').forEach(function (c) {
        if (c !== chip) c.classList.remove('open');
      });
      chip.classList.toggle('open');
    });
  });
  document.addEventListener('click', function () {
    document.querySelectorAll('.user-chip.open').forEach(function (c) { c.classList.remove('open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.user-chip.open').forEach(function (c) { c.classList.remove('open'); });
      setDrawer(false);
      closeLightbox();
    }
  });

  /* ---------- Reveal on scroll ---------- */
  var revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---------- Newsletter forms: save the subscription server-side ---------- */
  document.querySelectorAll('form.js-newsletter').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = form.querySelector('input[type="email"]');
      var email = input ? input.value.trim() : '';
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        toast('Please enter a valid email address.', 'fa-exclamation-circle');
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      fetch(form.getAttribute('action') || 'subscribe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
          'X-Requested-With': 'fetch'
        },
        body: 'email=' + encodeURIComponent(email)
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.ok) {
            form.reset();
            toast(data.message || 'Thank you! You\u2019re subscribed. God bless you!');
          } else {
            toast((data && data.message) || 'Sorry, we could not subscribe you right now.', 'fa-exclamation-circle');
          }
        })
        .catch(function () {
          toast('Sorry, we could not subscribe you right now.', 'fa-exclamation-circle');
        })
        .finally(function () {
          if (btn) btn.disabled = false;
        });
    });
  });

  /* ---------- Copy-to-clipboard helpers ---------- */
  document.querySelectorAll('.js-copy-num').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy') || btn.textContent.trim();
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { toast('Number copied \u2014 paste it in your MoMo app.'); });
      } else {
        var t = document.createElement('textarea');
        t.value = text; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); toast('Number copied \u2014 paste it in your MoMo app.'); } catch (e) {}
        t.remove();
      }
    });
  });

  /* ---------- Gallery lightbox ---------- */
  var lb = document.querySelector('.lightbox');
  function openLightbox(src, caption) {
    if (!lb) return;
    var img = lb.querySelector('img');
    var cap = lb.querySelector('figcaption');
    if (img) img.src = src;
    if (cap) cap.textContent = caption || '';
    lb.classList.add('open');
    document.body.classList.add('no-scroll');
  }
  function closeLightbox() {
    if (!lb) return;
    lb.classList.remove('open');
    document.body.classList.remove('no-scroll');
  }
  window.openLightbox = openLightbox;
  window.closeLightbox = closeLightbox;

  document.querySelectorAll('.js-lightbox').forEach(function (item) {
    item.addEventListener('click', function () {
      var img = item.querySelector('img');
      var cap = item.getAttribute('data-caption') || '';
      if (img) openLightbox(img.currentSrc || img.src, cap);
    });
  });
  if (lb) {
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLightbox(); });
    var lbClose = lb.querySelector('.lb-close');
    if (lbClose) lbClose.addEventListener('click', closeLightbox);
  }

  /* ---------- Live countdowns ---------- */
  document.querySelectorAll('[data-countdown]').forEach(function (el) {
    var end = new Date(el.getAttribute('data-countdown')).getTime();
    if (isNaN(end)) return;
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function tick() {
      var diff = end - Date.now();
      if (diff <= 0) { el.textContent = 'It\u2019s quiz time!'; return; }
      var d = Math.floor(diff / 864e5);
      var h = Math.floor(diff % 864e5 / 36e5);
      var m = Math.floor(diff % 36e5 / 6e4);
      var s = Math.floor(diff % 6e4 / 1e3);
      el.textContent = (d ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
    }
    tick();
    setInterval(tick, 1000);
  });
})();
