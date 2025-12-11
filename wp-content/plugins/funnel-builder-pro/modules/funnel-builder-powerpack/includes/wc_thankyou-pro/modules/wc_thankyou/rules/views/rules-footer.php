<?php
global $wfty_is_rules_saved; ?>
        <script type="text/template" id="wfty-rule-template-basic">
			<?php include 'metabox-rules-rule-template-basic.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath ?>

        </script>
        <script type="text/template" id="wfty-rule-template-product">

			<?php include 'metabox-rules-rule-template-product.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath ?>
        </script>

        <fieldset>

        </fieldset>
    </form>
      <div class="wfty_form_submit wfty_btm_grey_area wfty_clearfix">
	<div class="wfty_btm_save_wrap wfty_clearfix">
	   <span class="wfty_save_funnel_rules_ajax_loader spinner" style="opacity: 0"></span>

	</div>
      </div>
        <div class="wfty_success_modal" style="display: none" id="modal-rules-settings_success" data-iziModal-icon="icon-home">


        </div>
</div>