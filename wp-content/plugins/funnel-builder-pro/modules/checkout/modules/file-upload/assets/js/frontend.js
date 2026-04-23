/**
 * FunnelKit Checkout File Upload - Frontend JavaScript
 *
 * Handles drag-and-drop file upload, AJAX upload with progress, and file management.
 *
 * @package FunnelKit\Checkout\Modules\File_Upload
 */

(function($) {
	'use strict';

	/**
	 * File Upload Handler
	 */
	var WFACPFileUpload = {
		/**
		 * Initialize the file upload functionality.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind all event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Modern UI: Drag and drop events.
			$(document).on('dragenter dragover', '.wfacp-upload-dropzone', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).addClass('drag-over');
			});

			$(document).on('dragleave drop', '.wfacp-upload-dropzone', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('drag-over');
			});

			$(document).on('drop', '.wfacp-upload-dropzone', function(e) {
				var files = e.originalEvent.dataTransfer.files;
				self.handleFiles($(this), files);
			});

			// Click handlers use capture phase because the intlTelInput module
			// calls stopPropagation() on .woocommerce-input-wrapper clicks,
			// which prevents jQuery delegated handlers from reaching document.

			// Modern UI: Click anywhere on dropzone to browse.
			document.addEventListener('click', function(e) {
				var dropzone = e.target.closest('.wfacp-upload-dropzone');
				if (!dropzone) {
					return;
				}
				if (e.target.classList.contains('wfacp-upload-input') || e.target.classList.contains('wfacp-upload-select-btn')) {
					return;
				}
				var input = dropzone.querySelector('.wfacp-upload-input');
				if (input) {
					input.click();
				}
			}, true);

			// Modern UI: Select files button click.
			document.addEventListener('click', function(e) {
				var btn = e.target.closest('.wfacp-upload-select-btn');
				if (!btn) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				var dropzone = btn.closest('.wfacp-upload-dropzone');
				if (dropzone) {
					var input = dropzone.querySelector('.wfacp-upload-input');
					if (input) {
						input.click();
					}
				}
			}, true);

			// Button UI: Click to browse. When using label with for attribute, native behavior
			// activates the file input - no JS needed. This handler is a fallback for edge cases.
			document.addEventListener('click', function(e) {
				var btn = e.target.closest('.wfacp-upload-btn');
				if (!btn) {
					return;
				}
				// Label with for attribute uses native file dialog - let it work.
				if (btn.tagName === 'LABEL' && btn.htmlFor) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				var wrapper = btn.closest('.wfacp-upload-button-wrapper');
				if (wrapper) {
					var input = wrapper.querySelector('.wfacp-upload-input');
					if (input) {
						input.click();
					}
				}
			}, true);

			// File input change - works for both skins.
			$(document).on('change', '.wfacp-upload-input', function() {
				var $uploadZone = $(this).closest('.wfacp-upload-dropzone');
				if (!$uploadZone.length) {
					$uploadZone = $(this).closest('.wfacp-upload-button-wrapper');
				}
				self.handleFiles($uploadZone, this.files);
				// Reset input so same file can be selected again.
				$(this).val('');
			});

			// Delete file - capture phase for same stopPropagation reason.
			document.addEventListener('click', function(e) {
				var btn = e.target.closest('.wfacp-upload-delete');
				if (!btn) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				self.deleteFile($(btn));
			}, true);
		},

		/**
		 * Handle selected/dropped files.
		 *
		 * @param {jQuery} $dropzone The dropzone element.
		 * @param {FileList} files The files to upload.
		 */
		handleFiles: function($dropzone, files) {
			var self = this;
			var fieldKey = $dropzone.data('field-key');
			var maxSize = parseInt($dropzone.data('max-size'), 10) || 5;
			var maxFiles = parseInt($dropzone.data('max-files'), 10) || 5;
			var allowedTypes = ($dropzone.data('allowed-types') || 'pdf,jpg,jpeg,png,doc,docx').toLowerCase().split(',');
			var normalizedAllowedTypes = allowedTypes.map(function(type) {
				return $.trim(type);
			});
			var nonce = $dropzone.data('nonce');

			// Find wrapper - try multiple selectors for compatibility
			var $wrapper = $dropzone.closest('.wfacp-form-control');
			if (!$wrapper.length) {
				$wrapper = $dropzone.parent();
			}

			// Find file list - look in wrapper first, then sibling, then by field ID
			var $fileList = $wrapper.find('.wfacp-upload-file-list');
			if (!$fileList.length) {
				$fileList = $dropzone.siblings('.wfacp-upload-file-list');
			}
			if (!$fileList.length) {
				$fileList = $dropzone.parent().find('.wfacp-upload-file-list');
			}
			if (!$fileList.length && fieldKey) {
				// Try to find by field ID container
				var $fieldContainer = $('#' + fieldKey + '_field');
				if ($fieldContainer.length) {
					$fileList = $fieldContainer.find('.wfacp-upload-file-list');
				}
			}

			// Find hidden input
			var $hiddenInput = $wrapper.find('.wfacp-upload-hidden-input');
			if (!$hiddenInput.length) {
				$hiddenInput = $dropzone.siblings('.wfacp-upload-hidden-input');
			}
			if (!$hiddenInput.length) {
				$hiddenInput = $dropzone.parent().find('.wfacp-upload-hidden-input');
			}
			if (!$hiddenInput.length && fieldKey) {
				// Try by field ID
				$hiddenInput = $('#' + fieldKey + '_field').find('.wfacp-upload-hidden-input');
			}

			// Find errors container
			var $errors = $wrapper.find('.wfacp-upload-errors');
			if (!$errors.length) {
				$errors = $dropzone.siblings('.wfacp-upload-errors');
			}
			if (!$errors.length && fieldKey) {
				$errors = $('#' + fieldKey + '_field').find('.wfacp-upload-errors');
			}

			// Get current file count from DOM (more reliable than hidden input during concurrent uploads).
			var currentCount = this.countFilesInList($fileList);

			// Process each file.
			$.each(files, function(index, file) {
				// Check max files.
				if (currentCount >= maxFiles) {
					self.showError($errors, 'max-limit', self.getMaxFilesMessage(maxFiles), true);
					self.checkMaxFiles($dropzone, $hiddenInput, maxFiles);
					return false; // Break loop. Persistent message is shown by checkMaxFiles.
				}

				// Validate file type.
				var ext = file.name.split('.').pop().toLowerCase();
				if ($.inArray(ext, normalizedAllowedTypes) === -1) {
					self.showError($errors, 'invalid-type', self.getInvalidTypeMessage(normalizedAllowedTypes));
					return true; // Continue loop.
				}

				// Validate file size.
				if (file.size > maxSize * 1024 * 1024) {
					self.showError($errors, 'too-large', self.getFileTooLargeMessage(file.size, maxSize));
					return true; // Continue loop.
				}

				// Upload file.
				currentCount++;
				self.uploadFile($dropzone, file, nonce, $fileList, $hiddenInput, maxFiles);
			});
		},

		/**
		 * Upload a single file.
		 *
		 * @param {jQuery} $dropzone The dropzone element.
		 * @param {File} file The file to upload.
		 * @param {string} nonce The security nonce.
		 * @param {jQuery} $fileList The file list container.
		 * @param {jQuery} $hiddenInput The hidden input element.
		 * @param {number} maxFiles Maximum number of files.
		 */
		uploadFile: function($dropzone, file, nonce, $fileList, $hiddenInput, maxFiles) {
			var self = this;
			var fieldKey = $dropzone.data('field-key');
			var allowedTypes = $dropzone.data('allowed-types');
			var maxSize = $dropzone.data('max-size');

			// Create file row.
			var $row = this.createFileRow(file);

			// Make sure $fileList exists
			if ($fileList.length) {
				$fileList.append($row);
				// Check max files immediately after appending.
				this.checkMaxFiles($dropzone, $hiddenInput, maxFiles);
			} else {
				// Try to find it again
				var $wrapper = $dropzone.parent();
				$fileList = $wrapper.find('.wfacp-upload-file-list');
				if ($fileList.length) {
					$fileList.append($row);
					// Check max files immediately after appending.
					this.checkMaxFiles($dropzone, $hiddenInput, maxFiles);
				}
			}

			// Create FormData.
			var formData = new FormData();
			formData.append('action', 'wfacp_file_upload');
			formData.append('nonce', nonce);
			formData.append('file', file);
			formData.append('field_key', fieldKey);
			formData.append('checkout_id', $('input[name="_wfacp_post_id"]').val() || '');

			// Create XHR.
			var xhr = new XMLHttpRequest();
			$row.data('xhr', xhr);

			// Progress handler.
			xhr.upload.addEventListener('progress', function(e) {
				if (e.lengthComputable) {
					var percent = Math.round((e.loaded / e.total) * 100);
					self.updateProgress($row, percent);
				}
			});

			// Load handler.
			xhr.addEventListener('load', function() {
				if (xhr.status === 200) {
					try {
						var response = JSON.parse(xhr.responseText);
						if (response.success) {
							self.handleUploadSuccess($row, response.data, $hiddenInput, $dropzone, maxFiles);
						} else {
							self.handleUploadError($row, response.data.message || wfacpFileUpload.i18n.uploadError, self.getErrorStateClass(response.data.code));
						}
					} catch (e) {
						self.handleUploadError($row, wfacpFileUpload.i18n.uploadError, 'upload-error');
					}
				} else {
					self.handleUploadError($row, wfacpFileUpload.i18n.uploadError, 'upload-error');
				}
			});

			// Error handler.
			xhr.addEventListener('error', function() {
				self.handleUploadError($row, wfacpFileUpload.i18n.uploadError, 'upload-error');
			});

			// Abort handler.
			xhr.addEventListener('abort', function() {
				$row.remove();
				self.checkMaxFiles($dropzone, $hiddenInput, maxFiles);
			});

			// Send request.
			xhr.open('POST', wfacpFileUpload.ajaxUrl);
			xhr.send(formData);
		},

		/**
		 * Create a file row element.
		 *
		 * @param {File} file The file object.
		 * @return {jQuery} The file row element.
		 */
		createFileRow: function(file) {
			var ext = file.name.split('.').pop().toLowerCase();
			var fileIcon = this.getFileIconSvg(ext);
			var isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1;
			var deleteIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
			var fileSize = this.formatFileSize(file.size);

			var $row = $('<div class="wfacp-upload-file-row uploading">' +
				'<div class="wfacp-upload-file-preview">' +
				(isImage ? '<img src="" alt="" class="wfacp-upload-thumbnail">' : fileIcon) +
				'</div>' +
				'<div class="wfacp-upload-file-info">' +
				'<span class="wfacp-upload-file-name">' + this.truncateFilename(file.name, 30) + ' <span class="wfacp-upload-file-size">(' + fileSize + ')</span></span>' +
				'<div class="wfacp-upload-progress-bar"><div class="wfacp-upload-progress-fill"></div></div>' +
				'</div>' +
				'<div class="wfacp-upload-file-status">' +
				//'<span class="wfacp-upload-percent">0%</span>' +
				'<button type="button" class="wfacp-upload-delete">' + deleteIcon + '</button>' +
				'</div>' +
				'</div>');

			// Generate thumbnail for images.
			if (isImage && window.FileReader) {
				var reader = new FileReader();
				reader.onload = function(e) {
					$row.find('.wfacp-upload-thumbnail').attr('src', e.target.result);
				};
				reader.readAsDataURL(file);
			}

			return $row;
		},

		/**
		 * Get SVG icon for file type.
		 *
		 * @param {string} ext File extension.
		 * @return {string} SVG icon HTML.
		 */
		getFileIconSvg: function(ext) {
			// Default file icon SVG
			var defaultIcon = '<svg class="wfacp-file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
				'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>' +
				'<polyline points="14 2 14 8 20 8"></polyline>' +
				'</svg>';

			return defaultIcon;
		},

		/**
		 * Truncate filename if too long.
		 *
		 * @param {string} filename The filename.
		 * @param {number} maxLength Maximum length.
		 * @return {string} Truncated filename.
		 */
		truncateFilename: function(filename, maxLength) {
			if (filename.length <= maxLength) {
				return filename;
			}

			var ext = filename.split('.').pop();
			var name = filename.substring(0, filename.length - ext.length - 1);
			var truncated = name.substring(0, maxLength - ext.length - 4) + '...' + '.' + ext;

			return truncated;
		},

		/**
		 * Format file size in human-readable format.
		 *
		 * @param {number} bytes File size in bytes.
		 * @return {string} Formatted file size.
		 */
		formatFileSize: function(bytes) {
			if (bytes === 0) {
				return '0 B';
			}
			var units = ['B', 'KB', 'MB', 'GB'];
			var i = Math.floor(Math.log(bytes) / Math.log(1024));
			if (i >= units.length) {
				i = units.length - 1;
			}
			return parseFloat((bytes / Math.pow(1024, i)).toFixed(1)) + ' ' + units[i];
		},

		/**
		 * Update progress bar.
		 *
		 * @param {jQuery} $row The file row.
		 * @param {number} percent Progress percentage.
		 */
		updateProgress: function($row, percent) {
			$row.find('.wfacp-upload-progress-fill').css('width', percent + '%');
			$row.find('.wfacp-upload-percent').text(percent + '%');
		},

		/**
		 * Handle successful upload.
		 *
		 * @param {jQuery} $row The file row.
		 * @param {Object} data Response data.
		 * @param {jQuery} $hiddenInput The hidden input.
		 * @param {jQuery} $dropzone The dropzone.
		 * @param {number} maxFiles Maximum files.
		 */
		handleUploadSuccess: function($row, data, $hiddenInput, $dropzone, maxFiles) {
			$row.removeClass('uploading').addClass('uploaded');
			$row.find('.wfacp-upload-progress-bar').hide();
			$row.find('.wfacp-upload-percent').text('100%');

			var $errors = this.getErrorsContainer($dropzone, $row.closest('.wfacp-form-control'), $dropzone.data('field-key'));
			this.clearErrors($errors);

			// Store file data on row (including session_token for ownership verification on delete).
			$row.data('file-data', {
				url: data.url,
				name: data.name,
				hash: data.hash,
				session_token: data.session_token || ''
			});

			// If hidden input wasn't found earlier, try to find it now
			if (!$hiddenInput.length) {
				var fieldKey = $dropzone.data('field-key');
				$hiddenInput = $dropzone.siblings('.wfacp-upload-hidden-input');
				if (!$hiddenInput.length) {
					$hiddenInput = $dropzone.parent().find('.wfacp-upload-hidden-input');
				}
				if (!$hiddenInput.length && fieldKey) {
					$hiddenInput = $('input[name="' + fieldKey + '"]');
				}
			}

			// Update hidden input.
			this.updateHiddenInput($hiddenInput);

			// Clear validation error state after successful upload.
			var $formRow = $dropzone.closest('.form-row');
			if ($formRow.length) {
				$formRow.removeClass('woocommerce-invalid woocommerce-invalid-required-field').addClass('woocommerce-validated');
				// Remove any inline error messages for this field.
				var fieldKey = $dropzone.data('field-key');
				if (fieldKey) {
					$('.' + fieldKey + '_field_error').remove();
				}
			}

			// Check max files.
			this.checkMaxFiles($dropzone, $hiddenInput, maxFiles);
		},

		/**
		 * Handle upload error.
		 *
		 * @param {jQuery} $row The file row.
		 * @param {string} message Error message.
		 */
		handleUploadError: function($row, message, stateClass) {
			var errorType = stateClass || 'upload-error';
			var $wrapper = $row.closest('.wfacp-form-control');
			var $errors = this.getErrorsContainer($row, $wrapper);
			this.showError($errors, errorType, message);
			$row.fadeOut(200, function() {
				$(this).remove();
			});
		},

		/**
		 * Delete a file.
		 *
		 * @param {jQuery} $button The delete button.
		 */
		deleteFile: function($button) {
			var self = this;
			var $row = $button.closest('.wfacp-upload-file-row');
			var $fileList = $row.closest('.wfacp-upload-file-list');

			// Find wrapper and related elements
			var $wrapper = $button.closest('.wfacp-form-control');
			if (!$wrapper.length) {
				$wrapper = $fileList.parent();
			}

			var $dropzone = $wrapper.find('.wfacp-upload-dropzone, .wfacp-upload-button-wrapper');
			if (!$dropzone.length) {
				$dropzone = $fileList.siblings('.wfacp-upload-dropzone, .wfacp-upload-button-wrapper');
			}

			var $hiddenInput = $wrapper.find('.wfacp-upload-hidden-input');
			if (!$hiddenInput.length) {
				$hiddenInput = $fileList.siblings('.wfacp-upload-hidden-input');
			}

			var maxFiles = parseInt($dropzone.data('max-files'), 10) || 5;
			var nonce = $dropzone.data('nonce');

			// If still uploading, abort.
			if ($row.hasClass('uploading')) {
				var xhr = $row.data('xhr');
				if (xhr) {
					xhr.abort();
				}
				return;
			}

			// Get file data.
			var fileData = $row.data('file-data');
			if (!fileData) {
				$row.remove();
				return;
			}

			// Show removing state.
			$row.addClass('removing');
			$row.find('.wfacp-upload-delete').prop('disabled', true);

			// Send delete request.
			$.ajax({
				url: wfacpFileUpload.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wfacp_file_delete_upload',
					nonce: nonce,
					hash: fileData.hash,
					filename: fileData.name,
					session_token: fileData.session_token || ''
				},
				success: function() {
					$row.fadeOut(300, function() {
						$(this).remove();
						self.updateHiddenInput($hiddenInput);
						self.checkMaxFiles($dropzone, $hiddenInput, maxFiles);
					});
				},
				error: function() {
					// Still remove from UI even if server delete fails.
					$row.fadeOut(300, function() {
						$(this).remove();
						self.updateHiddenInput($hiddenInput);
						self.checkMaxFiles($dropzone, $hiddenInput, maxFiles);
					});
				}
			});
		},

		/**
		 * Get current file data from hidden input.
		 *
		 * @param {jQuery} $hiddenInput The hidden input.
		 * @return {Array} Array of file data objects.
		 */
		getFileData: function($hiddenInput) {
			var value = $hiddenInput.val();
			if (!value) {
				return [];
			}

			try {
				return JSON.parse(value);
			} catch (e) {
				return [];
			}
		},

		/**
		 * Count current files (both uploading and uploaded) in the file list.
		 *
		 * @param {jQuery} $fileList The file list container.
		 * @return {number} Number of files.
		 */
		countFilesInList: function($fileList) {
			if (!$fileList || !$fileList.length) {
				return 0;
			}
			return $fileList.find('.wfacp-upload-file-row').length;
		},

		/**
		 * Update hidden input with current files.
		 *
		 * @param {jQuery} $hiddenInput The hidden input.
		 */
		updateHiddenInput: function($hiddenInput) {
			if (!$hiddenInput || !$hiddenInput.length) {
				return;
			}

			// Find file list using multiple strategies
			var $wrapper = $hiddenInput.closest('.wfacp-form-control');
			var $fileList = $wrapper.length ? $wrapper.find('.wfacp-upload-file-list') : $();

			if (!$fileList.length) {
				$fileList = $hiddenInput.siblings('.wfacp-upload-file-list');
			}
			if (!$fileList.length) {
				$fileList = $hiddenInput.parent().find('.wfacp-upload-file-list');
			}
			if (!$fileList.length) {
				// Try by field ID
				var fieldId = $hiddenInput.attr('id');
				if (fieldId) {
					$fileList = $('#' + fieldId + '_field').find('.wfacp-upload-file-list');
				}
			}

			var files = [];

			if ($fileList.length) {
				$fileList.find('.wfacp-upload-file-row.uploaded').each(function() {
					var fileData = $(this).data('file-data');
					if (fileData) {
						files.push(fileData);
					}
				});
			}

			var jsonValue = files.length > 0 ? JSON.stringify(files) : '';
			$hiddenInput.val(jsonValue);
		},

		/**
		 * Check if max files reached and toggle dropzone.
		 *
		 * @param {jQuery} $dropzone The dropzone.
		 * @param {jQuery} $hiddenInput The hidden input.
		 * @param {number} maxFiles Maximum files.
		 */
		checkMaxFiles: function($dropzone, $hiddenInput, maxFiles) {
			var $wrapper = $dropzone.closest('.wfacp-form-control');
			if (!$wrapper.length) {
				$wrapper = $dropzone.parent();
			}

			var $fileList = $wrapper.find('.wfacp-upload-file-list');
			if (!$fileList.length) {
				$fileList = $dropzone.siblings('.wfacp-upload-file-list');
			}

			// Count files in DOM (includes uploading and uploaded).
			var currentCount = this.countFilesInList($fileList);
			// Find or create the max files message element.
			var $errors = $wrapper.find('.wfacp-upload-errors');
			if (!$errors.length) {
				$errors = $dropzone.siblings('.wfacp-upload-errors');
			}

			if (currentCount >= maxFiles) {
				$dropzone.addClass('max-reached');
				// Show max files reached message if not already shown.
				if ($errors.length && $errors.find('.wfacp-upload-max-msg').length === 0) {
					var msg = (typeof wfacpFileUpload !== 'undefined' && wfacpFileUpload.i18n && wfacpFileUpload.i18n.maxFilesReached)
						? wfacpFileUpload.i18n.maxFilesReached
						: 'Maximum number of files reached.';
					$errors.append(this.getErrorMarkup('max-limit', msg, true));
				}
			} else {
				$dropzone.removeClass('max-reached');
			}

			// Remove max files message when below limit.
			if ($errors.length && currentCount < maxFiles) {
				$errors.find('.wfacp-upload-max-msg').remove();
			}
		},

		/**
		 * Resolve the error container for a field.
		 *
		 * @param {jQuery} $context Primary lookup context.
		 * @param {jQuery} $wrapper Upload wrapper.
		 * @param {string} fieldKey Field key.
		 * @return {jQuery} Errors container.
		 */
		getErrorsContainer: function($context, $wrapper, fieldKey) {
			var $errors = $();

			if ($wrapper && $wrapper.length) {
				$errors = $wrapper.find('.wfacp-upload-errors');
			}
			if (!$errors.length && $context && $context.length) {
				$errors = $context.siblings('.wfacp-upload-errors');
			}
			if (!$errors.length && $context && $context.length) {
				$errors = $context.closest('.wfacp-file-upload-wrapper').find('.wfacp-upload-errors');
			}
			if (!$errors.length && fieldKey) {
				$errors = $('#' + fieldKey + '_field').find('.wfacp-upload-errors');
			}

			return $errors;
		},

		/**
		 * Clear field error messages.
		 *
		 * @param {jQuery} $errors Errors container.
		 */
		clearErrors: function($errors) {
			if (!$errors || !$errors.length) {
				return;
			}

			$errors.empty();
		},

		/**
		 * Show error message.
		 *
		 * @param {jQuery} $errors The errors container.
		 * @param {string} message The error message.
		 */
		showError: function($errors, type, message, persistent) {
			if (!$errors || !$errors.length) {
				return;
			}

			var dedupeSelector = '.wfacp-upload-error-' + type;
			if (type && $errors.find(dedupeSelector).length) {
				return;
			}
			var $error = $(this.getErrorMarkup(type, message, persistent));

			$errors.append($error);
		},

		/**
		 * Resolve named message template.
		 *
		 * @param {string} templateKey Template i18n key.
		 * @param {string} fallbackKey Fallback i18n key.
		 * @param {string} filename Filename to inject.
		 * @return {string} Resolved message.
		 */
		getErrorMarkup: function(type, message, persistent) {
			var icon = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.0013 1.66669C14.6043 1.66669 18.3359 5.39823 18.3359 10.0013C18.3359 14.6044 14.6043 18.3359 10.0013 18.3359C5.39817 18.3359 1.66663 14.6044 1.66663 10.0013C1.66663 5.39823 5.39817 1.66669 10.0013 1.66669ZM10.0013 2.91669C6.08852 2.91669 2.91663 6.08858 2.91663 10.0013C2.91663 13.914 6.08852 17.0859 10.0013 17.0859C13.914 17.0859 17.0859 13.914 17.0859 10.0013C17.0859 6.08858 13.914 2.91669 10.0013 2.91669ZM9.99822 8.75051C10.3146 8.7503 10.5763 8.98526 10.6179 9.29029L10.6236 9.3751L10.6266 13.9598C10.6269 14.3049 10.3472 14.5849 10.002 14.5852C9.68562 14.5854 9.42397 14.3504 9.38239 14.0454L9.37662 13.9606L9.37362 9.37591C9.3734 9.03074 9.65304 8.75073 9.99822 8.75051ZM10.0016 5.8357C10.4612 5.8357 10.8338 6.2083 10.8338 6.66792C10.8338 7.12754 10.4612 7.50014 10.0016 7.50014C9.542 7.50014 9.16941 7.12754 9.16941 6.66792C9.16941 6.2083 9.542 5.8357 10.0016 5.8357Z" fill="#E15334"/></svg>';
			var stateClass = type ? ' wfacp-upload-error-' + type : '';
			var persistClass = persistent ? ' wfacp-upload-error-persistent wfacp-upload-max-msg' : '';

			return '<div class="wfacp-upload-error' + stateClass + persistClass + '">' + icon + '<span class="wfacp-upload-error-text">' + message + '</span></div>';
		},

		/**
		 * Build invalid-type message from allowed types.
		 *
		 * @param {Array} allowedTypes Allowed extensions.
		 * @return {string} Message.
		 */
		getInvalidTypeMessage: function(allowedTypes) {
			var label = allowedTypes.join(', ').toUpperCase();
			if (wfacpFileUpload.i18n.invalidTypeDetail) {
				return wfacpFileUpload.i18n.invalidTypeDetail.replace('%1$s', label);
			}
			return wfacpFileUpload.i18n.invalidType;
		},

		/**
		 * Build too-large message with file size details.
		 *
		 * @param {number} sizeBytes File size.
		 * @param {number} maxSizeMb Max size in MB.
		 * @return {string} Message.
		 */
		getFileTooLargeMessage: function(sizeBytes, maxSizeMb) {
			if (wfacpFileUpload.i18n.fileTooLargeDetail) {
				return wfacpFileUpload.i18n.fileTooLargeDetail
					.replace('%1$s', this.formatFileSize(sizeBytes))
					.replace('%2$s', maxSizeMb);
			}
			return wfacpFileUpload.i18n.fileTooLarge;
		},

		/**
		 * Build max-file-count message.
		 *
		 * @param {number} maxFiles Maximum files.
		 * @return {string} Message.
		 */
		getMaxFilesMessage: function(maxFiles) {
			if (wfacpFileUpload.i18n.maxFilesReachedDetail) {
				return wfacpFileUpload.i18n.maxFilesReachedDetail.replace('%s', maxFiles);
			}
			return wfacpFileUpload.i18n.maxFilesReached;
		},

		/**
		 * Map backend error codes to UI state classes.
		 *
		 * @param {string} code Error code from backend.
		 * @return {string} CSS state class.
		 */
		getErrorStateClass: function(code) {
			switch (code) {
				case 'invalid_type':
					return 'invalid-type';
				case 'file_too_large':
					return 'too-large';
				case 'corrupted_file':
					return 'corrupted-file';
				default:
					return 'upload-error';
			}
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		WFACPFileUpload.init();
	});

})(jQuery);
