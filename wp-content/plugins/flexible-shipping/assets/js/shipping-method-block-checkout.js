( function() {
	const config = window.__fsShippingMethodLogoBlocks || {};

	const ensureArray = ( value ) => ( Array.isArray( value ) ? value : [] );

	const getMetaValue = ( metaData, key ) => {
		if ( ! Array.isArray( metaData ) ) {
			return null;
		}

		const item = metaData.find( ( entry ) => entry && entry.key === key );
		return item ? item.value : null;
	};

	const decodeBase64 = ( value ) => {
		if ( ! value || 'string' !== typeof value ) {
			return '';
		}

		try {
			return window.atob( value );
		} catch ( error ) {
			return '';
		}
	};

	const getRateDescription = ( rate ) => {
		if ( ! rate ) {
			return '';
		}

		const encodedDescription = getMetaValue( rate.meta_data, config.description_encoded_key || 'description_base64encoded' );
		if ( encodedDescription ) {
			const decodedDescription = decodeBase64( encodedDescription );
			if ( decodedDescription ) {
				return decodedDescription;
			}
		}

		return getMetaValue( rate.meta_data, config.description_key || 'description' ) || '';
	};

	const getRateId = ( rate ) => rate?.rate_id || rate?.id || '';

	const isFlexibleShippingRate = ( rate ) => {
		if ( ! rate ) {
			return false;
		}

		return ensureArray( config.method_ids ).includes( rate.method_id );
	};

	const escapeCssValue = ( value ) => {
		if ( window.CSS && 'function' === typeof window.CSS.escape ) {
			return window.CSS.escape( value );
		}

		return value.replace( /["\\]/g, '\\$&' );
	};

	const getOptionForRate = ( rateId ) => {
		if ( ! rateId ) {
			return null;
		}

		const selector = `.wc-block-components-radio-control__option input[value="${ escapeCssValue( rateId ) }"]`;
		const input = document.querySelector( selector );

		return input ? input.closest( '.wc-block-components-radio-control__option' ) : null;
	};

	const findDescriptionElement = ( option, input ) => {
		if ( input ) {
			const describedBy = input.getAttribute( 'aria-describedby' );
			if ( describedBy ) {
				const descriptionIds = describedBy.split( /\s+/ ).filter( Boolean );

				for ( const id of descriptionIds ) {
					const element = document.getElementById( id );
					if ( element ) {
						return element;
					}
				}
			}
		}

		return option ? option.querySelector( '[id$="__secondary-description"], [id$="__description"]' ) : null;
	};

	const getInsertionAnchor = ( option, input ) => {
		if ( ! option ) {
			return null;
		}

		const labelGroup = option.querySelector( '.wc-block-components-radio-control__label-group' );
		if ( labelGroup ) {
			return labelGroup;
		}

		return findDescriptionElement( option, input ) || option.querySelector( 'label' ) || option;
	};

	const createLogoNode = ( logoUrl, logoAlt ) => {
		const wrapper = document.createElement( 'span' );
		wrapper.classList.add( config.wrapper_class || 'flexible-shipping-method-logo-block' );
		wrapper.style.display = 'block';
		wrapper.style.marginTop = '6px';

		const image = document.createElement( 'img' );
		image.src = logoUrl;
		image.alt = logoAlt || '';
		image.style.maxHeight = '40px';
		image.style.width = 'auto';
		image.loading = 'lazy';

		wrapper.appendChild( image );

		return wrapper;
	};

	const createDescriptionNode = ( description ) => {
		const wrapper = document.createElement( 'div' );
		wrapper.className = config.description_class || 'shipping-method-description flexible-shipping-method-description-block';
		wrapper.innerHTML = description;

		return wrapper;
	};

	const getRatesFromCart = ( cartData ) => {
		if ( ! cartData || ! Array.isArray( cartData.shippingRates ) ) {
			return [];
		}

		const rates = [];

		cartData.shippingRates.forEach( ( shippingPackage ) => {
			const packageRates = shippingPackage?.shipping_rates ?? [];
			packageRates.forEach( ( rate ) => rates.push( rate ) );
		} );

		return rates;
	};

	const renderLogos = () => {
		if ( ! window.wp || ! window.wp.data || 'function' !== typeof window.wp.data.select ) {
			return;
		}

		const cartStore = window.wp.data.select( 'wc/store/cart' );
		if ( ! cartStore || 'function' !== typeof cartStore.getCartData ) {
			return;
		}

		const rates = getRatesFromCart( cartStore.getCartData() );
		if ( ! rates.length ) {
			return;
		}

		rates.forEach( ( rate ) => {
			if ( ! isFlexibleShippingRate( rate ) ) {
				return;
			}

			const description = getRateDescription( rate );
			const logoUrl = getMetaValue( rate.meta_data, config.logo_url_key || 'method_logo_url' );
			const option = getOptionForRate( getRateId( rate ) );
			if ( ! option ) {
				return;
			}

			const wrapperClass = config.wrapper_class || 'flexible-shipping-method-logo-block';
			const descriptionClass = ( config.description_class || 'shipping-method-description flexible-shipping-method-description-block' )
				.split( ' ' )
				.filter( Boolean )
				.map( ( className ) => `.${ className }` )
				.join( '' );
			const existingDescription = descriptionClass ? option.querySelector( descriptionClass ) : null;
			const existingLogo = option.querySelector( `.${ wrapperClass }` );
			const input = option.querySelector( 'input' );
			const insertionAnchor = getInsertionAnchor( option, input );
			const logoAlt = getMetaValue( rate.meta_data, config.logo_alt_key || 'method_logo_alt' ) || '';

			if ( description ) {
				if ( existingDescription ) {
					if ( existingDescription.innerHTML !== description ) {
						existingDescription.innerHTML = description;
					}
				} else if ( insertionAnchor ) {
					insertionAnchor.insertAdjacentElement( 'afterend', createDescriptionNode( description ) );
				}
			} else if ( existingDescription ) {
				existingDescription.remove();
			}

			const descriptionAnchor = option.querySelector( descriptionClass ) || insertionAnchor;

			if ( ! logoUrl ) {
				if ( existingLogo ) {
					existingLogo.remove();
				}
				return;
			}

			if ( existingLogo ) {
				const existingImage = existingLogo.querySelector( 'img' );

				if ( existingImage ) {
					if ( existingImage.src !== logoUrl ) {
						existingImage.src = logoUrl;
					}

					if ( existingImage.alt !== logoAlt ) {
						existingImage.alt = logoAlt;
					}
				}

				if ( descriptionAnchor && existingLogo.previousElementSibling !== descriptionAnchor ) {
					descriptionAnchor.insertAdjacentElement( 'afterend', existingLogo );
				}

				return;
			}

			const logoNode = createLogoNode( logoUrl, logoAlt );

			if ( descriptionAnchor ) {
				descriptionAnchor.insertAdjacentElement( 'afterend', logoNode );
				return;
			}

			const label = option.querySelector( 'label' ) || option;
			label.appendChild( logoNode );
		} );
	};

	const boot = () => {
		let scheduled = false;

		const scheduleRender = () => {
			if ( scheduled ) {
				return;
			}

			scheduled = true;
			window.requestAnimationFrame( () => {
				scheduled = false;
				renderLogos();
			} );
		};

		if ( window.wp && window.wp.data && 'function' === typeof window.wp.data.subscribe ) {
			window.wp.data.subscribe( scheduleRender );
		}

		new MutationObserver( scheduleRender ).observe( document.body, {
			childList: true,
			subtree: true,
		} );

		scheduleRender();
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
