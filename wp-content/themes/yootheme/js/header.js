import { $, before, css, hasAttr, matches, observeResize, offset, remove, toPx } from 'uikit-util';

const Section = {
    computed: {
        section: {
            get: () =>
                $('.tm-header ~ [class*="uk-section"], .tm-header ~ * > [class*="uk-section"]'),
            observe: () => '.tm-page',
        },
    },

    watch: {
        section() {
            this.$emit();
        },
    },
};

export const Header = {
    mixins: [Section],

    computed: {
        anchor: {
            get() {
                return (
                    this.section &&
                    !matches(this.section, '[tm-header-transparent-noplaceholder]') &&
                    ($('.uk-grid,.uk-panel:not(.uk-container)', this.section) || $('.tm-main > *'))
                );
            },
        },
    },

    observe: [
        {
            observe: observeResize,
            handler() {
                this.$emit();
            },
        },
    ],

    watch: {
        anchor() {
            this.$emit();
        },

        section(section, prev) {
            prev && this.$update();
        },
    },

    update: [
        {
            read() {
                return { height: this.$el.offsetHeight };
            },

            write({ height }) {
                if (!height || !this.anchor) {
                    remove(this.placeholder);
                    return;
                }

                this.placeholder ||= $(
                    '<div class="tm-header-placeholder uk-margin-remove-adjacent">'
                );

                if (this.anchor.previousElementSibling !== this.placeholder) {
                    before(this.anchor, this.placeholder);
                }

                css(this.placeholder, { height });
            },
        },
    ],
};

export const Sticky = {
    mixins: [Section],

    update: {
        read() {
            return (
                this.section &&
                hasAttr(this.$el, 'tm-section-start') && {
                    start:
                        this.section.offsetHeight <= toPx('100vh')
                            ? offset(this.section).bottom
                            : offset(this.section).top + 300,
                }
            );
        },

        events: ['resize'],
    },
};
