export const CORE_NOTICE = 'core/notices';

// NOTE: the BOGO notices are no longer dispatched into the core/notices store — they are
// rendered by components/Notices/BOGODeals so WooCommerce 11's block store-notice
// sanitizer cannot strip their markup — so there is no BOGO id to sweep here.
export const ID_NOTICE = {
  ACFWF_NOTICE_CART_CONDITIONS: 'ACFWF_NOTICE_CART_CONDITIONS',
  ACFWF_NOTICE_STORE_CREDIT: 'ACFWF_NOTICE_STORE_CREDIT',
  ACFWF_NOTICE_STORE_CREDIT_GUEST: 'ACFWF_NOTICE_STORE_CREDIT_GUEST',
};
