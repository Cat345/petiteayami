/**
 * Edit Order screen: a heading line shows its box's item count and total
 * instead of the zero-priced placeholder it is stored as.
 */
(function () {
	"use strict";

	function apply(scope) {
		scope.querySelectorAll(".wffn-bundles-heading-sum").forEach(function (sum) {
			var row = sum.closest("tr.item");
			if (!row || row.dataset.wffnBundlesSummed) return;
			row.dataset.wffnBundlesSummed = "1";
			var cost = row.querySelector("td.item_cost .view");
			var qty = row.querySelector("td.quantity .view");
			var total = row.querySelector("td.line_cost .view");
			if (cost) cost.textContent = "";
			var refundedQty = Number(sum.dataset.refundedQty || 0);
			if (qty) qty.innerHTML = '<small class="times">&times;</small> ' + Number(sum.dataset.qty || 1) + (refundedQty ? '<small class="refunded">' + refundedQty + "</small>" : "");
			if (total) total.innerHTML = (sum.dataset.total || "") + (sum.dataset.refunded ? '<small class="refunded">' + sum.dataset.refunded + "</small>" : "");
		});
	}

	function start() {
		var box = document.getElementById("woocommerce-order-items");
		if (!box) return;
		apply(box);
		// The items table is re-rendered over AJAX after an edit or a refund.
		new MutationObserver(function () { apply(box); }).observe(box, { childList: true, subtree: true });
	}

	if ("loading" === document.readyState) document.addEventListener("DOMContentLoaded", start);
	else start();
})();
