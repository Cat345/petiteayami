/*
 * Thickbox 3.1 - One Box To Rule Them All.
 * By Cody Lindley (http://www.codylindley.com)
 * Copyright (c) 2007 cody lindley
 * Licensed under the MIT License: http://www.opensource.org/licenses/mit-license.php
*/

if ( typeof wfocu_modal_pathToImage != 'string' ) {
	var wfocu_modal_pathToImage = (typeof wfocumodal10n !== "undefined")?wfocumodal10n.loadingAnimation:"";
}

/*!!!!!!!!!!!!!!!!! edit below this line at your own risk !!!!!!!!!!!!!!!!!!!!!!!*/

//on page load call wfocu_modal_init
jQuery(document).ready(function(){
	wfocu_modal_init('a.wfocumodal, area.wfocumodal, input.wfocumodal');//pass where to apply wfocumodal
	imgLoader = new Image();// preload image
	imgLoader.src = wfocu_modal_pathToImage;
});

/*
 * Add wfocumodal to href & area elements that have a class of .wfocumodal.
 * Remove the loading indicator when content in an iframe has loaded.
 */
function wfocu_modal_init(domChunk){
	jQuery( 'body' )
		.on( 'click', domChunk, wfocu_modal_click )
		.on( 'wfocumodal:iframe:loaded', function() {
			jQuery( '#WFOCU_MB_window' ).removeClass( 'wfocumodal-loading' );
		});
}

function wfocu_modal_click(){
	var t = this.title || this.name || null;
	var a = this.href || this.alt;
	var g = this.rel || false;
	wfocu_modal_show(t,a,g);
	this.blur();
	return false;
}
function wfocu_show_tb(title, id) {
    wfocu_modal_show(title, "#WFOCU_MB_inline?height=500&amp;width=1000&amp;inlineId=" + id + "");
}
function wfocu_modal_show(caption, url, imageGroup) {//function called when the user clicks on a wfocumodal link

	var $closeBtn;

	try {
		if (typeof document.body.style.maxHeight === "undefined") {//if IE 6
			jQuery("body","html").css({height: "100%", width: "100%"});
			jQuery("html").css("overflow","hidden");
			if (document.getElementById("WFOCU_MB_HideSelect") === null) {//iframe to hide select elements in ie6
				jQuery("body").append("<iframe id='WFOCU_MB_HideSelect'>"+wfocumodal10n.noiframes+"</iframe><div id='WFOCU_MB_overlay'></div><div id='WFOCU_MB_window' class='wfocumodal-loading'></div>");
				jQuery("#WFOCU_MB_overlay").click(wfocu_modal_remove);
			}
		}else{//all others
			if(document.getElementById("WFOCU_MB_overlay") === null){
				jQuery("body").append("<div id='WFOCU_MB_overlay'></div><div id='WFOCU_MB_window' class='wfocumodal-loading'></div>");
				jQuery("#WFOCU_MB_overlay").click(wfocu_modal_remove);
				jQuery( 'body' ).addClass( 'modal-open' );
			}
		}

		if(wfocu_modal_detectMacXFF()){
			jQuery("#WFOCU_MB_overlay").addClass("WFOCU_MB_overlayMacFFBGHack");//use png overlay so hide flash
		}else{
			jQuery("#WFOCU_MB_overlay").addClass("WFOCU_MB_overlayBG");//use background and opacity
		}

		if(caption===null){caption="";}
		jQuery("body").append("<div id='WFOCU_MB_load'><img src='"+imgLoader.src+"' width='208' /></div>");//add loader to the page
		jQuery('#WFOCU_MB_load').show();//show loader

		var baseURL;
	   if(url.indexOf("?")!==-1){ //ff there is a query string involved
			baseURL = url.substr(0, url.indexOf("?"));
	   }else{
	   		baseURL = url;
	   }

	   var urlString = /\.jpg$|\.jpeg$|\.png$|\.gif$|\.bmp$/;
	   var urlType = baseURL.toLowerCase().match(urlString);

		if(urlType == '.jpg' || urlType == '.jpeg' || urlType == '.png' || urlType == '.gif' || urlType == '.bmp'){//code to show images

			WFOCU_MB_PrevCaption = "";
			WFOCU_MB_PrevURL = "";
			WFOCU_MB_PrevHTML = "";
			WFOCU_MB_NextCaption = "";
			WFOCU_MB_NextURL = "";
			WFOCU_MB_NextHTML = "";
			WFOCU_MB_imageCount = "";
			WFOCU_MB_FoundURL = false;
			if(imageGroup){
				WFOCU_MB_TempArray = jQuery("a[rel="+imageGroup+"]").get();
				for (WFOCU_MB_Counter = 0; ((WFOCU_MB_Counter < WFOCU_MB_TempArray.length) && (WFOCU_MB_NextHTML === "")); WFOCU_MB_Counter++) {
					var urlTypeTemp = WFOCU_MB_TempArray[WFOCU_MB_Counter].href.toLowerCase().match(urlString);
						if (!(WFOCU_MB_TempArray[WFOCU_MB_Counter].href == url)) {
							if (WFOCU_MB_FoundURL) {
								WFOCU_MB_NextCaption = WFOCU_MB_TempArray[WFOCU_MB_Counter].title;
								WFOCU_MB_NextURL = WFOCU_MB_TempArray[WFOCU_MB_Counter].href;
								WFOCU_MB_NextHTML = "<span id='WFOCU_MB_next'>&nbsp;&nbsp;<a href='#'>"+wfocumodal10n.next+"</a></span>";
							} else {
								WFOCU_MB_PrevCaption = WFOCU_MB_TempArray[WFOCU_MB_Counter].title;
								WFOCU_MB_PrevURL = WFOCU_MB_TempArray[WFOCU_MB_Counter].href;
								WFOCU_MB_PrevHTML = "<span id='WFOCU_MB_prev'>&nbsp;&nbsp;<a href='#'>"+wfocumodal10n.prev+"</a></span>";
							}
						} else {
							WFOCU_MB_FoundURL = true;
							WFOCU_MB_imageCount = wfocumodal10n.image + ' ' + (WFOCU_MB_Counter + 1) + ' ' + wfocumodal10n.of + ' ' + (WFOCU_MB_TempArray.length);
						}
				}
			}

			imgPreloader = new Image();
			imgPreloader.onload = function(){
			imgPreloader.onload = null;

			// Resizing large images - original by Christian Montoya edited by me.
			var pagesize = wfocu_modal_getPageSize();
			var x = pagesize[0] - 150;
			var y = pagesize[1] - 150;
			var imageWidth = imgPreloader.width;
			var imageHeight = imgPreloader.height;
			if (imageWidth > x) {
				imageHeight = imageHeight * (x / imageWidth);
				imageWidth = x;
				if (imageHeight > y) {
					imageWidth = imageWidth * (y / imageHeight);
					imageHeight = y;
				}
			} else if (imageHeight > y) {
				imageWidth = imageWidth * (y / imageHeight);
				imageHeight = y;
				if (imageWidth > x) {
					imageHeight = imageHeight * (x / imageWidth);
					imageWidth = x;
				}
			}
			// End Resizing

			WFOCU_MB_WIDTH = imageWidth + 30;
			WFOCU_MB_HEIGHT = imageHeight + 60;
			jQuery("#WFOCU_MB_window").append("<a href='' id='WFOCU_MB_ImageOff'><span class='screen-reader-text'>"+wfocumodal10n.close+"</span><img id='WFOCU_MB_Image' src='"+url+"' width='"+imageWidth+"' height='"+imageHeight+"' alt='"+caption+"'/></a>" + "<div id='WFOCU_MB_caption'>"+caption+"<div id='WFOCU_MB_secondLine'>" + WFOCU_MB_imageCount + WFOCU_MB_PrevHTML + WFOCU_MB_NextHTML + "</div></div><div id='WFOCU_MB_closeWindow'><button type='button' id='WFOCU_MB_closeWindowButton'><span class='screen-reader-text'>"+wfocumodal10n.close+"</span><span class='wfocu_modal_close_btn'></span></button></div>");

			jQuery("#WFOCU_MB_closeWindowButton").click(wfocu_modal_remove);

			if (!(WFOCU_MB_PrevHTML === "")) {
				function goPrev(){
					if(jQuery(document).unbind("click",goPrev)){jQuery(document).unbind("click",goPrev);}
					jQuery("#WFOCU_MB_window").remove();
					jQuery("body").append("<div id='WFOCU_MB_window'></div>");
					wfocu_modal_show(WFOCU_MB_PrevCaption, WFOCU_MB_PrevURL, imageGroup);
					return false;
				}
				jQuery("#WFOCU_MB_prev").click(goPrev);
			}

			if (!(WFOCU_MB_NextHTML === "")) {
				function goNext(){
					jQuery("#WFOCU_MB_window").remove();
					jQuery("body").append("<div id='WFOCU_MB_window'></div>");
					wfocu_modal_show(WFOCU_MB_NextCaption, WFOCU_MB_NextURL, imageGroup);
					return false;
				}
				jQuery("#WFOCU_MB_next").click(goNext);

			}

			jQuery(document).bind('keydown.wfocumodal', function(e){
				if ( e.which == 27 ){ // close
					wfocu_modal_remove();

				} else if ( e.which == 190 ){ // display previous image
					if(!(WFOCU_MB_NextHTML == "")){
						jQuery(document).unbind('wfocumodal');
						goNext();
					}
				} else if ( e.which == 188 ){ // display next image
					if(!(WFOCU_MB_PrevHTML == "")){
						jQuery(document).unbind('wfocumodal');
						goPrev();
					}
				}
				return false;
			});

			wfocu_modal_position();
			jQuery("#WFOCU_MB_load").remove();
			jQuery("#WFOCU_MB_ImageOff").click(wfocu_modal_remove);
			jQuery("#WFOCU_MB_window").css({'visibility':'visible'}); //for safari using css instead of show
			};

			imgPreloader.src = url;
		}else{//code to show html

			var queryString = url.replace(/^[^\?]+\??/,'');
			var params = wfocu_modal_parseQuery( queryString );

			WFOCU_MB_WIDTH = (params['width']*1) + 30 || 630; //defaults to 630 if no parameters were added to URL
			WFOCU_MB_HEIGHT = (params['height']*1) + 40 || 440; //defaults to 440 if no parameters were added to URL
			ajaxContentW = WFOCU_MB_WIDTH - 30;
			ajaxContentH = WFOCU_MB_HEIGHT - 45;

			if(url.indexOf('WFOCU_MB_iframe') != -1){// either iframe or ajax window
					urlNoQuery = url.split('WFOCU_MB_');
					jQuery("#WFOCU_MB_iframeContent").remove();
					if(params['modal'] != "true"){//iframe no modal
						jQuery("#WFOCU_MB_window").append("<div id='WFOCU_MB_title'><div id='WFOCU_MB_ajaxWindowTitle'>"+caption+"</div><div id='WFOCU_MB_closeAjaxWindow'><button type='button' id='WFOCU_MB_closeWindowButton'><span class='screen-reader-text'>"+wfocumodal10n.close+"</span><span class='wfocu_modal_close_btn'></span></button></div></div><iframe frameborder='0' hspace='0' allowtransparency='true' src='"+urlNoQuery[0]+"' id='WFOCU_MB_iframeContent' name='WFOCU_MB_iframeContent"+Math.round(Math.random()*1000)+"' onload='wfocu_modal_showIframe()' style='width:"+(ajaxContentW + 29)+"px;height:"+(ajaxContentH + 17)+"px;' >"+wfocumodal10n.noiframes+"</iframe>");
					}else{//iframe modal
					jQuery("#WFOCU_MB_overlay").unbind();
						jQuery("#WFOCU_MB_window").append("<iframe frameborder='0' hspace='0' allowtransparency='true' src='"+urlNoQuery[0]+"' id='WFOCU_MB_iframeContent' name='WFOCU_MB_iframeContent"+Math.round(Math.random()*1000)+"' onload='wfocu_modal_showIframe()' style='width:"+(ajaxContentW + 29)+"px;height:"+(ajaxContentH + 17)+"px;'>"+wfocumodal10n.noiframes+"</iframe>");
					}
			}else{// not an iframe, ajax
					if(jQuery("#WFOCU_MB_window").css("visibility") != "visible"){
						if(params['modal'] != "true"){//ajax no modal
						jQuery("#WFOCU_MB_window").append("<div id='WFOCU_MB_title'><div id='WFOCU_MB_ajaxWindowTitle'>"+caption+"</div><div id='WFOCU_MB_closeAjaxWindow'><a href='#' id='WFOCU_MB_closeWindowButton'><div class='wfocu_modal_close_btn'></div></a></div></div><div id='WFOCU_MB_ajaxContent' style='width:"+ajaxContentW+"px;height:"+ajaxContentH+"px'></div>");
						}else{//ajax modal
						jQuery("#WFOCU_MB_overlay").unbind();
						jQuery("#WFOCU_MB_window").append("<div id='WFOCU_MB_ajaxContent' class='WFOCU_MB_modal' style='width:"+ajaxContentW+"px;height:"+ajaxContentH+"px;'></div>");
						}
					}else{//this means the window is already up, we are just loading new content via ajax
						jQuery("#WFOCU_MB_ajaxContent")[0].style.width = ajaxContentW +"px";
						jQuery("#WFOCU_MB_ajaxContent")[0].style.height = ajaxContentH +"px";
						jQuery("#WFOCU_MB_ajaxContent")[0].scrollTop = 0;
						jQuery("#WFOCU_MB_ajaxWindowTitle").html(caption);
					}
			}

			jQuery("#WFOCU_MB_closeWindowButton").click(wfocu_modal_remove);

				if(url.indexOf('WFOCU_MB_inline') != -1){
					jQuery("#WFOCU_MB_ajaxContent").append(jQuery('#' + params['inlineId']).children());
					jQuery("#WFOCU_MB_window").bind('wfocu_modal_unload', function () {
						jQuery('#' + params['inlineId']).append( jQuery("#WFOCU_MB_ajaxContent").children() ); // move elements back when you're finished
					});
					wfocu_modal_position();
					jQuery("#WFOCU_MB_load").remove();
					jQuery("#WFOCU_MB_window").css({'visibility':'visible'});
				}else if(url.indexOf('WFOCU_MB_iframe') != -1){
					wfocu_modal_position();
					jQuery("#WFOCU_MB_load").remove();
					jQuery("#WFOCU_MB_window").css({'visibility':'visible'});
				}else{
					var load_url = url;
					load_url += -1 === url.indexOf('?') ? '?' : '&';
					jQuery("#WFOCU_MB_ajaxContent").load(load_url += "random=" + (new Date().getTime()),function(){//to do a post change this load method
						wfocu_modal_position();
						jQuery("#WFOCU_MB_load").remove();
						wfocu_modal_init("#WFOCU_MB_ajaxContent a.wfocumodal");
						jQuery("#WFOCU_MB_window").css({'visibility':'visible'});
					});
				}

		}

		if(!params['modal']){
			jQuery(document).bind('keydown.wfocumodal', function(e){
				if ( e.which == 27 ){ // close
					wfocu_modal_remove();
					return false;
				}
			});
		}

		$closeBtn = jQuery( '#WFOCU_MB_closeWindowButton' );
		/*
		 * If the native Close button icon is visible, move focus on the button
		 * (e.g. in the Network Admin Themes screen).
		 * In other admin screens is hidden and replaced by a different icon.
		 */
		if ( $closeBtn.find( '.wfocu_modal_close_btn' ).is( ':visible' ) ) {
			$closeBtn.focus();
		}
                    
              
                if(jQuery("#WFOCU_MB_ajaxContent").innerHeight() > window.innerHeight){
                    jQuery("#WFOCU_MB_ajaxContent").height( (window.innerHeight * 90) / 100 );
                }

	} catch(e) {
		//nothing here
	}
}

//helper functions below
function wfocu_modal_showIframe(){
	jQuery("#WFOCU_MB_load").remove();
	jQuery("#WFOCU_MB_window").css({'visibility':'visible'}).trigger( 'wfocumodal:iframe:loaded' );
}

function wfocu_modal_remove() {
 	jQuery("#WFOCU_MB_imageOff").unbind("click");
	jQuery("#WFOCU_MB_closeWindowButton").unbind("click");
	jQuery( '#WFOCU_MB_window' ).fadeOut( 'fast', function() {
		jQuery( '#WFOCU_MB_window, #WFOCU_MB_overlay, #WFOCU_MB_HideSelect' ).trigger( 'wfocu_modal_unload' ).unbind().remove();
		jQuery( 'body' ).trigger( 'wfocumodal:removed' );
	});
	jQuery( 'body' ).removeClass( 'modal-open' );
	jQuery("#WFOCU_MB_load").remove();
	if (typeof document.body.style.maxHeight == "undefined") {//if IE 6
		jQuery("body","html").css({height: "auto", width: "auto"});
		jQuery("html").css("overflow","");
	}
	jQuery(document).unbind('.wfocumodal');
	return false;
}

function wfocu_modal_position() {
var isIE6 = typeof document.body.style.maxHeight === "undefined";
jQuery("#WFOCU_MB_window").css({marginLeft: '-' + parseInt((WFOCU_MB_WIDTH / 2),10) + 'px', width: WFOCU_MB_WIDTH + 'px'});
	if ( ! isIE6 ) { // take away IE6
		jQuery("#WFOCU_MB_window").css({marginTop: '-' + parseInt((WFOCU_MB_HEIGHT / 2),10) + 'px'});
	}
}

function wfocu_modal_parseQuery ( query ) {
   var Params = {};
   if ( ! query ) {return Params;}// return empty object
   var Pairs = query.split(/[;&]/);
   for ( var i = 0; i < Pairs.length; i++ ) {
      var KeyVal = Pairs[i].split('=');
      if ( ! KeyVal || KeyVal.length != 2 ) {continue;}
      var key = unescape( KeyVal[0] );
      var val = unescape( KeyVal[1] );
      val = val.replace(/\+/g, ' ');
      Params[key] = val;
   }
   return Params;
}

function wfocu_modal_getPageSize(){
	var de = document.documentElement;
	var w = window.innerWidth || self.innerWidth || (de&&de.clientWidth) || document.body.clientWidth;
	var h = window.innerHeight || self.innerHeight || (de&&de.clientHeight) || document.body.clientHeight;
	arrayPageSize = [w,h];
	return arrayPageSize;
}

function wfocu_modal_detectMacXFF() {
  var userAgent = navigator.userAgent.toLowerCase();
  if (userAgent.indexOf('mac') != -1 && userAgent.indexOf('firefox')!=-1) {
    return true;
  }
}

