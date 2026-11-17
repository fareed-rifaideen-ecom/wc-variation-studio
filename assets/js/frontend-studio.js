(function ($) {
  'use strict';

  const config = window.wcvsConfig || {};

  class WcvsGalleryController {
    constructor(productEl) {
      this.productEl = productEl;
      this.galleryWrap = productEl.querySelector('.woocommerce-product-gallery');
      this.initialPayload = this.parseInitialPayload();
      this.currentSlides = [];

      if (this.galleryWrap && this.initialPayload && Array.isArray(this.initialPayload.slides) && this.initialPayload.slides.length) {
        this.mountCustomGallery(this.initialPayload.slides);
      }
    }

    parseInitialPayload() {
      const scriptEl = document.getElementById('wcvs-initial-gallery-data');
      if (!scriptEl) return null;
      try {
        return JSON.parse(scriptEl.textContent);
      } catch (e) {
        return null;
      }
    }

    mountCustomGallery(slides) {
      this.currentSlides = slides;
      this.galleryWrap.classList.add('wcvs-gallery-enhanced');
      this.galleryWrap.innerHTML = `
        <div class="wcvs-gallery-stage" role="region" aria-label="Product Gallery">
          <div class="wcvs-gallery-track">
            ${slides.map((s, idx) => this.renderMainSlide(s, idx)).join('')}
          </div>
          ${slides.length > 1 ? `
            <button type="button" class="wcvs-nav-btn wcvs-prev" aria-label="Previous image">&#8249;</button>
            <button type="button" class="wcvs-nav-btn wcvs-next" aria-label="Next image">&#8250;</button>
          ` : ''}
        </div>
        ${slides.length > 1 ? `
          <div class="wcvs-thumb-strip" role="tablist">
            ${slides.map((s, idx) => this.renderThumbSlide(s, idx)).join('')}
          </div>
        ` : ''}
      `;

      this.bindGalleryEvents();
    }

    renderMainSlide(slide, index) {
      if (slide.type === 'video') {
        return `
          <div class="wcvs-slide wcvs-slide-video" data-index="${index}">
            ${this.renderVideoEmbed(slide)}
          </div>
        `;
      }
      return `
        <div class="wcvs-slide" data-index="${index}">
          <img src="${slide.src}"
               ${slide.srcset ? `srcset="${slide.srcset}"` : ''}
               ${slide.sizes ? `sizes="${slide.sizes}"` : ''}
               alt="${this.escapeAttr(slide.alt || '')}"
               width="${slide.width || ''}"
               height="${slide.height || ''}"
               loading="${index === 0 ? 'eager' : 'lazy'}" />
        </div>
      `;
    }

    renderThumbSlide(slide, index) {
      const activeClass = index === 0 ? 'is-active' : '';
      const thumbUrl = slide.thumb_src || slide.src || slide.poster_src || '';
      return `
        <button type="button"
                class="wcvs-thumb-btn ${activeClass}"
                role="tab"
                aria-selected="${index === 0 ? 'true' : 'false'}"
                data-index="${index}">
          <img src="${thumbUrl}" alt="" loading="lazy" />
          ${slide.type === 'video' ? '<span class="wcvs-thumb-play-badge">&#9654;</span>' : ''}
        </button>
      `;
    }

    renderVideoEmbed(slide) {
      if (slide.provider === 'youtube') {
        const match = slide.video_url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
        const id = match ? match[1] : '';
        return id
          ? `<iframe src="https://www.youtube-nocookie.com/embed/${id}?rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>`
          : '';
      }
      if (slide.provider === 'vimeo') {
        const match = slide.video_url.match(/vimeo\.com\/(\d+)/);
        const id = match ? match[1] : '';
        return id
          ? `<iframe src="https://player.vimeo.com/video/${id}" frameborder="0" allow="autoplay; fullscreen" allowfullscreen loading="lazy"></iframe>`
          : '';
      }
      return `<video controls playsinline preload="metadata" ${slide.poster_src ? `poster="${slide.poster_src}"` : ''}><source src="${slide.video_url}"></video>`;
    }

    bindGalleryEvents() {
      const track = this.galleryWrap.querySelector('.wcvs-gallery-track');
      const thumbs = Array.from(this.galleryWrap.querySelectorAll('.wcvs-thumb-btn'));
      const prevBtn = this.galleryWrap.querySelector('.wcvs-prev');
      const nextBtn = this.galleryWrap.querySelector('.wcvs-next');
      let currentIndex = 0;

      const goTo = (idx) => {
        if (!track || !this.currentSlides.length) return;
        currentIndex = (idx + this.currentSlides.length) % this.currentSlides.length;
        track.scrollTo({ left: track.clientWidth * currentIndex, behavior: 'smooth' });
        thumbs.forEach((btn, i) => {
          btn.classList.toggle('is-active', i === currentIndex);
          btn.setAttribute('aria-selected', i === currentIndex ? 'true' : 'false');
        });
      };

      thumbs.forEach((btn) => {
        btn.addEventListener('click', () => {
          const idx = parseInt(btn.dataset.index, 10);
          goTo(idx);

          // Reverse Thumbnail-to-Swatch Sync when viewing initial dynamic gallery.
          const slide = this.currentSlides[idx];
          if (config.reverseGallerySync && slide && slide.match_attributes) {
            this.applyMatchingAttributes(slide.match_attributes);
          }
        });
      });

      if (prevBtn) prevBtn.addEventListener('click', () => goTo(currentIndex - 1));
      if (nextBtn) nextBtn.addEventListener('click', () => goTo(currentIndex + 1));
    }

    applyMatchingAttributes(attrMap) {
      const form = this.productEl.querySelector('form.variations_form');
      if (!form) return;

      Object.entries(attrMap).forEach(([attrName, value]) => {
        const select = form.querySelector(`select[name="${CSS.escape(attrName)}"]`);
        if (select && select.value !== value) {
          select.value = value;
          $(select).trigger('change');
        }
      });
    }

    resetToInitial() {
      if (this.initialPayload && this.initialPayload.slides) {
        this.mountCustomGallery(this.initialPayload.slides);
      }
    }

    escapeAttr(str) {
      return String(str).replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }
  }

  class WcvsSwatchFormController {
    constructor(formEl, galleryController) {
      this.formEl = formEl;
      this.$form = $(formEl);
      this.gallery = galleryController;
      this.bindEvents();
      this.syncSwatchVisualState();
    }

    bindEvents() {
      // Swatch click & keyboard selection -> drives WooCommerce's native <select>.
      this.formEl.addEventListener('click', (e) => {
        const item = e.target.closest('.wcvs-swatch-item');
        if (!item || item.classList.contains('is-disabled')) return;
        this.toggleSwatchItem(item);
      });

      this.formEl.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const item = e.target.closest('.wcvs-swatch-item');
        if (!item || item.classList.contains('is-disabled')) return;
        e.preventDefault();
        this.toggleSwatchItem(item);
      });

      // WooCommerce native variation lifecycle hooks.
      this.$form.on('woocommerce_update_variation_values', () => this.syncSwatchVisualState());

      this.$form.on('found_variation', (_event, variation) => {
        this.syncSwatchVisualState();
        if (this.gallery && Array.isArray(variation.wcvs_gallery) && variation.wcvs_gallery.length) {
          this.gallery.mountCustomGallery(variation.wcvs_gallery);
        }
        if (config.urlDeepLink) {
          this.syncBrowserUrl();
        }
      });

      this.$form.on('reset_data', () => {
        this.syncSwatchVisualState();
        if (this.gallery) {
          this.gallery.resetToInitial();
        }
        if (config.urlDeepLink) {
          this.syncBrowserUrl(true);
        }
      });
    }

    toggleSwatchItem(item) {
      const wrapper = item.closest('.wcvs-swatch-wrapper');
      const select = wrapper ? wrapper.querySelector('select') : null;
      if (!select) return;

      const value = item.dataset.value || '';
      select.value = select.value === value ? '' : value;
      $(select).trigger('change');
    }

    syncSwatchVisualState() {
      this.formEl.querySelectorAll('.wcvs-swatch-wrapper').forEach((wrapper) => {
        const select = wrapper.querySelector('select');
        if (!select) return;

        const activeVal = select.value;
        const availableValues = new Set(
          Array.from(select.options)
            .filter((opt) => opt.value !== '' && !opt.disabled)
            .map((opt) => opt.value)
        );

        wrapper.querySelectorAll('.wcvs-swatch-item').forEach((item) => {
          const val = item.dataset.value || '';
          const isSelected = val === activeVal && val !== '';
          const isAvailable = availableValues.has(val);

          item.classList.toggle('is-selected', isSelected);
          item.setAttribute('aria-checked', isSelected ? 'true' : 'false');
          item.classList.toggle('is-disabled', !isAvailable);
          item.classList.toggle(`wcvs-oos-${config.outOfStockBehavior || 'cross'}`, !isAvailable);
        });
      });
    }

    syncBrowserUrl(clear = false) {
      if (!window.history || !window.history.replaceState) return;
      const url = new URL(window.location.href);
      this.formEl.querySelectorAll('select[name^="attribute_"]').forEach((select) => {
        if (clear || !select.value) {
          url.searchParams.delete(select.name);
        } else {
          url.searchParams.set(select.name, select.value);
        }
      });
      window.history.replaceState({}, '', url.toString());
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const productEl = document.querySelector('.single-product .product');
    const galleryController = productEl ? new WcvsGalleryController(productEl) : null;

    document.querySelectorAll('form.variations_form').forEach((formEl) => {
      new WcvsSwatchFormController(formEl, galleryController);
    });
  });
})(jQuery);
