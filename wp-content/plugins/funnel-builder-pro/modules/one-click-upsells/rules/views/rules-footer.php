<?php
global $wfocu_is_rules_saved; ?>
        <script type="text/template" id="wfocu-rule-template-basic">
			<?php include 'metabox-rules-rule-template-basic.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath ?>

        </script>
        <script type="text/template" id="wfocu-rule-template-product">

			<?php include 'metabox-rules-rule-template-product.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath ?>
        </script>

        <fieldset>

        </fieldset>
    </form>
      <div class="wfocu_form_submit wfocu_btm_grey_area wfocu_clearfix">
	<div class="wfocu_btm_save_wrap wfocu_clearfix">
	   <span class="wfocu_save_funnel_rules_ajax_loader spinner" style="opacity: 0"></span>

	</div>
      </div>
        <div class="wfocu_success_modal" style="display: none" id="modal-rules-settings_success" data-iziModal-icon="icon-home">


        </div>
</div>