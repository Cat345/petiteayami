/**
 * FunnelKit Checkout File Upload - Admin JavaScript
 *
 * Extends the FunnelKit page builder edit field modal with file upload settings.
 * Uses wfacp.hooks API to add custom fields.
 *
 * @package FunnelKit\Checkout\Modules\File_Upload
 */

(function($) {
	'use strict';

	/**
	 * File Upload Admin Handler
	 */
	var WFACPFileUploadAdmin = {
		/**
		 * Field type identifier.
		 */
		fieldType: 'wfacp_file_upload',

		/**
		 * Initialize the admin functionality.
		 */
		init: function() {
			var self = this;

			// Wait for wfacp.hooks to be available.
			if (typeof wfacp === 'undefined' || typeof wfacp.hooks === 'undefined') {
				setTimeout(function() {
					self.init();
				}, 100);
				return;
			}

			this.registerHooks();
		},

		/**
		 * Register all hooks.
		 */
		registerHooks: function() {
			var self = this;

			// Add custom fields to the edit modal.
			wfacp.hooks.addFilter('wfacp_editable_fields', function(fields) {
				return self.addEditableFields(fields);
			});

			// Persist custom properties on save.
			wfacp.hooks.addFilter('wfacp_before_field_save', function(data, model) {
				return self.beforeFieldSave(data, model);
			});

			// Merge field data with model.
			wfacp.hooks.addFilter('wfacp_field_data_merge_with_model', function(model, data) {
				return self.mergeFieldData(model, data);
			});
		},

		/**
		 * Add custom editable fields to the edit modal.
		 *
		 * @param {Array} fields Existing fields.
		 * @return {Array} Modified fields.
		 */
		addEditableFields: function(fields) {
			var self = this;
			var i18n = wfacpFileUploadAdmin.i18n;
			var defaults = wfacpFileUploadAdmin.defaults;

			// Max file size field.
			fields.push({
				type: 'input',
				inputType: 'number',
				label: i18n.maxsize,
				model: 'maxsize',
				styleClasses: 'wfacp_required_cls',
				default: defaults.maxsize,
				visible: function(model) {
					return model.field_type === self.fieldType;
				}
			});

			// Accepted file types field.
			fields.push({
				type: 'input',
				inputType: 'text',
				label: i18n.acceptedFileTypes,
				model: 'accepted_file_types',
				placeholder: i18n.acceptedFileTypesHint,
				hint: i18n.acceptedFileTypesHint,
				styleClasses: 'wfacp_required_cls',
				default: defaults.accepted_file_types,
				visible: function(model) {
					return model.field_type === self.fieldType;
				}
			});

			// Max files field.
			fields.push({
				type: 'input',
				inputType: 'number',
				label: i18n.maxFiles,
				model: 'max_files',
				styleClasses: 'wfacp_required_cls',
				default: defaults.max_files,
				visible: function(model) {
					return model.field_type === self.fieldType;
				}
			});

			// Order meta data checkbox.
			fields.push({
				type: 'checkbox',
				label: i18n.orderMetaData,
				model: 'order_meta_data',
				styleClasses: 'wfacp_required_cls',
				default: defaults.order_meta_data,
				visible: function(model) {
					return model.field_type === self.fieldType;
				}
			});

			// User meta data checkbox.
			fields.push({
				type: 'checkbox',
				label: i18n.userMetaData,
				model: 'user_meta_data',
				styleClasses: 'wfacp_required_cls',
				default: defaults.user_meta_data,
				visible: function(model) {
					return model.field_type === self.fieldType;
				}
			});

			return fields;
		},

		/**
		 * Persist custom properties before saving.
		 *
		 * @param {Object} data Field data to save.
		 * @param {Object} model Current model.
		 * @return {Object} Modified data.
		 */
		beforeFieldSave: function(data, model) {
			// Only process file upload fields.
			if (model.field_type !== this.fieldType) {
				return data;
			}

			// Ensure custom properties are included in save data.
			var customProps = [
				'maxsize',
				'accepted_file_types',
				'max_files',
				'order_meta_data',
				'user_meta_data'
			];

			customProps.forEach(function(prop) {
				if (model.hasOwnProperty(prop)) {
					data[prop] = model[prop];
				}
			});

			return data;
		},

		/**
		 * Merge field data with model when editing.
		 *
		 * @param {Object} model The model object.
		 * @param {Object} data The field data.
		 * @return {Object} Modified model.
		 */
		mergeFieldData: function(model, data) {
			// Only process file upload fields.
			if (data.field_type !== this.fieldType) {
				return model;
			}

			var defaults = wfacpFileUploadAdmin.defaults;

			// Set default values if not present.
			if (!model.hasOwnProperty('maxsize') || model.maxsize === '') {
				model.maxsize = data.maxsize || defaults.maxsize;
			}

			if (!model.hasOwnProperty('accepted_file_types') || model.accepted_file_types === '') {
				model.accepted_file_types = data.accepted_file_types || defaults.accepted_file_types;
			}

			if (!model.hasOwnProperty('max_files') || model.max_files === '') {
				model.max_files = data.max_files || defaults.max_files;
			}

			if (!model.hasOwnProperty('order_meta_data')) {
				model.order_meta_data = data.hasOwnProperty('order_meta_data') ? data.order_meta_data : defaults.order_meta_data;
			}

			if (!model.hasOwnProperty('user_meta_data')) {
				model.user_meta_data = data.hasOwnProperty('user_meta_data') ? data.user_meta_data : defaults.user_meta_data;
			}

			return model;
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		WFACPFileUploadAdmin.init();
	});

})(jQuery);
