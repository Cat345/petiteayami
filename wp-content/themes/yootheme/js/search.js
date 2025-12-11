import UIkit from 'uikit';
import { $, $$, css, isVisible, observeIntersection, selFocusable, toggleClass } from 'uikit-util';

UIkit.component('Search', {
    args: 'target',

    props: {
        target: String,
        mode: Boolean,
        preventSubmit: Boolean,
    },

    data: {
        target: null,
        mode: false,
        preventSubmit: false,
    },

    computed: {
        target: ({ target }) => $(target),

        dropdown({ mode }) {
            const dropdown = mode ? null : this.target.closest('.uk-drop');
            return this.$getComponent(dropdown, 'drop') || this.$getComponent(dropdown, 'dropdown');
        },
    },

    observe: [
        {
            target: (vm) => (vm.mode ? vm.$el.form.parentElement : vm.target),
            observe: observeIntersection,
            handler(records) {
                if (records.some((record) => record.isIntersecting)) {
                    this.updateForm(true);
                    if (this.$el.value && !this._index) {
                        this.update();
                    }
                }
            },
        },
    ],

    events: [
        {
            name: 'submit',
            capture: true,
            filter: (vm) => vm.preventSubmit,
            delegate: () => 'form[role="search"]',
            el: (vm) => vm.$el.form.parentElement,
            handler: (e) => e.preventDefault(),
        },
        {
            name: 'input',
            handler() {
                this.show();
            },
        },
        {
            name: 'input',
            el: (vm) => vm.target,
            delegate: () => 'input[type="search"]',
            handler(e) {
                if (e.target !== this.$el) {
                    this.$el.value = e.target.value;
                    this.show();
                }
            },
        },
        {
            name: 'focus',
            filter: (vm) => !vm.mode,
            handler() {
                this.showDropdown();
            },
        },
        {
            name: 'keydown',
            filter: (vm) => !vm.mode,
            handler(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!this.dropdown.isToggled()) {
                        this.showDropdown();
                    }
                    if (isVisible(this.target)) {
                        $(selFocusable, this.target)?.focus();
                    }
                }
            },
        },
    ],

    methods: {
        async show() {
            await this.update();
            this.showDropdown();
        },

        async update() {
            this._index ??= 0;
            const index = ++this._index;

            const html = await this.query();

            if (index !== this._index) {
                return;
            }

            const { activeElement } = this.$el.ownerDocument;
            const inputs = $$('input[type="search"]', this.target);
            const focused = inputs.findIndex((el) => el === activeElement);

            setInnerHtml(this.target, html);

            const newInputs = $$('input[type="search"]', this.target);
            if (newInputs[focused]) {
                newInputs[focused].replaceWith(inputs[focused]);
                inputs[focused].focus();
            }

            this.updateForm();
        },

        updateForm(focus) {
            if (!this.mode) {
                return;
            }

            const input = $$('input[type="search"]', this.target).find(isVisible);

            this.$el.form.hidden = !!input;
            toggleClass(this.$el.form, 'uk-margin-remove-adjacent', !!input);

            if (input) {
                input.autofocus = true;
                if (focus) {
                    input.setSelectionRange(input.value.length, input.value.length);
                }
            }
        },

        async query() {
            const { form } = this.$el;
            const formData = new FormData(form);
            const data = Object.entries(JSON.parse(form.dataset.liveSearch || '{}'));

            for (const [key, value] of [...data, ['live-search', true]]) {
                formData.append(key, value);
            }

            try {
                const response = await fetch(form.action, { method: 'POST', body: formData });

                if (response.ok) {
                    return await response.text();
                }
            } catch {}
        },

        showDropdown() {
            if (!this.dropdown) {
                return;
            }

            if (hasVisibleChildren(this.target)) {
                this.dropdown.show(this.$el.form.parentElement, false);
            } else {
                this.dropdown.hide(false);
            }
        },
    },
});

function hasVisibleChildren(el) {
    return Array.from(el.children).some(
        (child) =>
            css(child, 'display') !== 'none' &&
            (hasVisibleChildren(child) || !child.children.length)
    );
}

const scripts = new Set();
function setInnerHtml(target, html) {
    for (const el of $$('script')) {
        scripts.add(el.src);
    }

    target.innerHTML = html;

    for (const el of $$('script', target)) {
        if (!scripts.has(el.src)) {
            el.remove();
            target.append(cloneScript(el));
        }
    }
}

function cloneScript(node) {
    const script = document.createElement('script');
    script.text = node.innerHTML;

    for (const { name, value } of node.attributes) {
        script.setAttribute(name, value);
    }
    return script;
}
