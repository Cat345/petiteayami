/**
 * Funnel Builder (Aero Checkout) — fold a bundle's member lines in the order summaries.
 *
 * The toggle and each row's state arrive in the server's markup. A click flips
 * them in its own summary only and records the folded groups in a cookie, which
 * the server reads when Aero redraws; each summary's own state is kept for the
 * visit and put back after every redraw.
 */
(function () {
	'use strict';

	var COOKIE = 'wffn_bundles_aero_collapsed';
	var MAX_GROUPS = 20;
	var GROUP = /^[A-Za-z0-9_-]{1,40}$/;

	function folded() {
		var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]).split('.').filter(function (g) { return GROUP.test(g); }) : [];
	}

	function remember(groups) {
		document.cookie = COOKIE + '=' + encodeURIComponent(groups.slice(-MAX_GROUPS).join('.')) + '; path=/; SameSite=Lax';
	}

	function each(selector, fn) {
		Array.prototype.forEach.call(document.querySelectorAll(selector), fn);
	}

	var DURATION = 260;
	var EASING = 'cubic-bezier(0.4, 0, 0.2, 1)';
	var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Put back what wrap() moved, and drop the box.
	function unwrap(row) {
		(row._wffnBoxes || []).forEach(function (box) {
			while (box.firstChild) {
				box.parentNode.insertBefore(box.firstChild, box);
			}
			box.parentNode.removeChild(box);
		});
		row._wffnBoxes = null;
	}

	// A table row cannot animate its height, and a cell's bare text escapes any child's
	// max-height, so each cell's whole content goes into one clipping box for the move.
	function wrap(row) {
		unwrap(row);
		row._wffnBoxes = Array.prototype.map.call(row.cells, function (td) {
			var box = document.createElement('div');
			box.className = 'wffn-bundles-sum-anim';
			box.style.overflow = 'hidden';
			while (td.firstChild) {
				box.appendChild(td.firstChild);
			}
			td.appendChild(box);
			return box;
		});
		return row._wffnBoxes;
	}

	function slide(row, fold, done) {
		var boxes = wrap(row);
		var moving = [];
		Array.prototype.forEach.call(row.cells, function (td, i) {
			var cs = window.getComputedStyle(td);
			var open = { paddingTop: cs.paddingTop, paddingBottom: cs.paddingBottom };
			var shut = { paddingTop: '0px', paddingBottom: '0px' };
			moving.push(td.animate(fold ? [open, shut] : [shut, open], { duration: DURATION, easing: EASING }));
			var h = boxes[i].scrollHeight + 'px';
			var frames = [{ height: h, opacity: 1 }, { height: '0px', opacity: 0 }];
			moving.push(boxes[i].animate(fold ? frames : frames.reverse(), { duration: DURATION, easing: EASING }));
		});
		row._wffnMoving = moving;
		Promise.all(moving.map(function (a) { return a.finished; })).then(function () {
			row._wffnMoving = null;
			unwrap(row);
			done();
		}, function () {});
	}

	// Stop a move in flight and restore the cells at once, so a new one starts clean.
	function stop(row) {
		(row._wffnMoving || []).forEach(function (a) { a.cancel(); });
		row._wffnMoving = null;
		unwrap(row);
	}

	// Which summary a table is, by its place among the summaries listing that bundle:
	// templates differ in their markup, but each redraw keeps the summaries in order.
	function slot(table, group) {
		var tables = [];
		each('.wffn-bundles-sum-toggle[data-wffn-bundles-group="' + group + '"]', function (b) {
			var t = b.closest('table');
			if (t && tables.indexOf(t) < 0) {
				tables.push(t);
			}
		});
		return tables.indexOf(table);
	}

	var STORE = 'wffn_bundles_aero_folds';

	function states() {
		try {
			return JSON.parse(window.sessionStorage.getItem(STORE) || '{}') || {};
		} catch (err) {
			return {};
		}
	}

	function save(map) {
		try {
			window.sessionStorage.setItem(STORE, JSON.stringify(map));
		} catch (err) {}
	}

	// Fold or open one bundle in one summary only: folding the other summary too moved
	// everything under the tapped link on a phone, where the other one sits above it.
	function show(group, fold, table, animate) {
		Array.prototype.forEach.call(table.querySelectorAll('.wffn-bundles-sum-toggle[data-wffn-bundles-group="' + group + '"]'), function (b) {
			b.setAttribute('aria-expanded', fold ? 'false' : 'true');
		});
		Array.prototype.forEach.call(table.querySelectorAll('tr.wffn-bundles-cart-child.wffn-bundles-cart-group-' + group), function (row) {
			var busy = !!row._wffnMoving;
			stop(row);
			// A redraw already in the right state: nothing to move.
			if (!busy && row.classList.contains('wffn-bundles-cart-collapsed') === fold) {
				return;
			}
			if (!animate || REDUCED || !row.animate || table.getBoundingClientRect().height === 0) {
				row.classList.toggle('wffn-bundles-cart-collapsed', fold);
				return;
			}
			if (fold) {
				slide(row, true, function () {
					row.classList.add('wffn-bundles-cart-collapsed');
				});
				return;
			}
			row.classList.remove('wffn-bundles-cart-collapsed');
			slide(row, false, function () {});
		});
	}

	// Keeps the tapped link under the finger if anything above it still moves.
	function pin(btn, top) {
		var until = Date.now() + DURATION + 100;
		(function hold() {
			var drift = btn.getBoundingClientRect().top - top;
			if (drift) {
				window.scrollBy(0, drift);
			}
			if (Date.now() < until) {
				window.requestAnimationFrame(hold);
			}
		})();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target && e.target.closest ? e.target.closest('.wffn-bundles-sum-toggle') : null;
		var group = btn ? btn.getAttribute('data-wffn-bundles-group') : '';
		var table = btn ? btn.closest('table') : null;
		if (!btn || !table || !GROUP.test(group)) {
			return;
		}
		e.preventDefault();
		var fold = 'true' === btn.getAttribute('aria-expanded');
		var top = btn.getBoundingClientRect().top;
		show(group, fold, table, true);
		pin(btn, top);
		// Every summary's state is kept, not only the tapped one's: the server redraws them
		// all from the last tap, and restore() puts the untouched ones back as they were.
		var map = states();
		each('.wffn-bundles-sum-toggle[data-wffn-bundles-group="' + group + '"]', function (b) {
			var t = b.closest('table');
			if (t) {
				map[group + '|' + slot(t, group)] = t === table ? fold : 'false' === b.getAttribute('aria-expanded');
			}
		});
		save(map);
		// The server's redraw starts from the last tap; restore() puts each summary back.
		var groups = folded().filter(function (g) { return g !== group; });
		if (fold) {
			groups.push(group);
		}
		remember(groups);
	});

	// Each summary keeps its own state through Aero's redraws, set at once, before the
	// browser paints, so a summary the shopper did not touch never moves.
	function restore() {
		var map = states();
		each('.wffn-bundles-sum-toggle[data-wffn-bundles-group]', function (b) {
			var group = b.getAttribute('data-wffn-bundles-group');
			var table = b.closest('table');
			var key = table ? group + '|' + slot(table, group) : '';
			if (GROUP.test(group) && key && Object.prototype.hasOwnProperty.call(map, key)) {
				show(group, !!map[key], table, false);
			}
		});
	}

	restore();
	if (window.jQuery) {
		window.jQuery(document.body).on('updated_checkout', restore);
	}
})();
