<div class="bwf_breadcrumb">
	<div class="bwf_before_bre"></div>
	<?php echo BWF_Admin_Breadcrumbs::render(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="bwf_after_bre">
		<a v-on:click="updateExperiment()" href="javascript:void(0);" class="bwf_edt">
			<i class="dashicons dashicons-edit"></i> <?php esc_html_e( 'Edit', 'woofunnels-ab-tests' ); ?>
		</a>
		<a v-if="`4`!=experiment_status" v-bind:data-status="experiment_status" v-on:click="(`2`==experiment_status)?stopExperiment():startExperiment()" href="javascript:void(0);" class="<?php echo esc_attr( $btnClass ); ?> wfabt_btn_small">
			<i v-if="`2`==experiment_status" class="dashicons dashicons-controls-pause"></i>
			<span v-if="`1`==experiment_status" class="dashicons dashicons-controls-play"></span>
			<span v-if="`3`==experiment_status" class="dashicons dashicons-update"></span> <?php echo esc_html( $btn_text ); ?>
		</a>
	</div>
	<div class="bwfab_status_wrap"><span class="wfabt_status_btn" data-status="<?php echo $experiment->get_status(); ?>" ><?php echo $experiment->get_status_nice_name(); ?></span>
	</div>
</div>
