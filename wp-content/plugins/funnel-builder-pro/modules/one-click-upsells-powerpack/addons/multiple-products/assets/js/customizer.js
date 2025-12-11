(function ($) {
		"use strict";

		wfocuCust.modify_desc_list_ltext_css = function ($val, $prefix, $suffix) {
			$val.desktop = (parseInt($val.desktop) + 4);
			$val.tablet = (parseInt($val.tablet) + 4);
			$val.mobile = (parseInt($val.mobile) + 4);
			return $val;
		};
	}
)(jQuery);



