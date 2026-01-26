jQuery(document).ready(function($){

	var historyTable = $('#xoo-wl-history-table').DataTable({
		"order": [
			[0, 'desc']
		],
		"columnDefs": [ {
			"targets"  : 'no-sort',
	      	"orderable": false,
	    }]
	});


	$('body').on('click', '.xoo-wl-remove-row', function(e){

		e.preventDefault();

		var $tr 		= $(this).closest('tr'),
			trNotice 	= new TableRowNotice( $tr ),
			rowID 		= $(this).attr('data-row_id'),
			productID 	= null;


		trNotice.addNotice( xoo_wl_admin_table_localize.strings.deleting );

		$.ajax({
			url: xoo_wl_admin_table_localize.adminurl,
			type: 'POST',
			data: {
				'action': 'xoo_wl_table_remove_row',
				'rowID': rowID,
				'productID': productID,
				'xoo_wl_nonce': xoo_wl_admin_table_localize.nonce
			},
			success: function(response){

				if( response.notice ){
					trNotice.addNotice( response.notice );
				}

				setTimeout(function(){
					if( !response.error ){
						dataTable.row( $tr ).remove().draw();
					}
					else{
						$tr.html( trNotice.$trClone );
					}
				}, response.notice ? 5000 : 0 );
				

				if( response.count ){
					$('.xoo-wl-ut-ucount span').html(response.count['rowsCount']);
					$('.xoo-wl-ut-qcount span').html(response.count['totalQuantity']);  
				}
			}
		});
	})



	function TableRowNotice( $tr ){

		this.$tr 		= $tr;
		this.$trClone 	= $tr.html();
		this.colspan 	= $tr.closest('table').find('tr').first().children('th, td').length;

		this.addNotice = function( notice ){
			this.$tr.html( '<td class="xoo-wl-tr-notice" colspan="'+this.colspan+'">'+ notice +'</td>' );
		}

		this.setToDefault = function(){
			this.$tr.html( $trClone );
		}

		this.afterNotice = function( callback, noticeTime ){
			setTimeout( callback, noticeTime );
		}
	}


	//Send Email
	$( 'body' ).on( 'click', '.xoo-wl-bis-btn', function(){

		var formData = {
			'action': 'xoo_wl_table_send_email',
			'xoo_wl_nonce': xoo_wl_admin_table_localize.nonce
		};

		var $tr 		= $(this).closest('tr'),
			trNotice 	= new TableRowNotice( $tr )

		if( $(this).attr('data-row_id') ){
			formData['rowID'] 	= $(this).attr('data-row_id');
		}

		if( $(this).attr('data-product_id') ){
			formData['productID'] 	= $(this).attr('data-product_id');
		}

		trNotice.addNotice(xoo_wl_admin_table_localize.strings.sending);

		$.ajax({
			url: xoo_wl_admin_table_localize.adminurl,
			type: 'POST',
			data: formData,
			success: function(response){

				if( response.notice ){
					trNotice.addNotice(response.notice);
				}

				setTimeout( function(){
					if( response.delete_row ){
						$tr.remove();
					}
					else{
						$tr.html( trNotice.$trClone );
						if( response.sent_count && $tr.find( '.xoo-wl-sent-count' ) ){
							$tr.find('.xoo-wl-sent-count').html( '('+ response.sent_count +')' );
						}
					}
				}, response.notice ? 10000 : 0 )
				

				if( response.count ){
					$('.xoo-wl-ut-ucount span').html(response.count['rowsCount']);
					$('.xoo-wl-ut-qcount span').html(response.count['totalQuantity']);  
				}
			}
		});
	} )



	function showTableNotice( $notice, timeout = 5000 ){

		var $noticeCont = $('.xoo-wl-notices');

		$noticeCont.show().html($notice);

		setTimeout(function(){
			$noticeCont.hide();
		}, timeout );
	}


	//Export CSV toggle form
	$('.xoo-wl-exp-toogle').click(function(){
		var $cont = $(this).closest('.xoo-wl-exp-container');
		$cont.addClass('xoo-wl-exp-active');
		$(this).closest('.xoo-wl-exim-cont').addClass('xoo-wl-active');
	})

	//Import CSV toggle form
	$('.xoo-wl-imp-toogle').click(function(){
		$(this).hide();
		$('.xoo-wl-imp-form').show();
		$(this).closest('.xoo-wl-exim-cont').addClass('xoo-wl-active');
	})



	$('#doaction, #doaction2').on('click', function(e){

		var action = $(this).closest('form').find('select[name^=\"action\"]').val();

		if ( action === 'delete' ) {

			if ( ! confirm('Are you sure you want to delete the selected waitlist entries? This cannot be undone.') ) {
				e.preventDefault();
				return false;
			}
		}
	});

})