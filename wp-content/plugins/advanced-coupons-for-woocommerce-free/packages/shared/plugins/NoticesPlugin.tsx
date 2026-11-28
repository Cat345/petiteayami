// Import library.
import { dispatch } from '../../shared/library/StoreAPI';
import { WC_CHECKOUT, WC_CART } from '../../shared/library/Context';
import { CORE_NOTICE, ID_NOTICE } from '../../shared/wc-block/wc_block_notice';
import CartConditions from '../components/Notices/CartConditions';
import BOGODeals from '../components/Notices/BOGODeals';
import StoreCredit from '../components/Notices/StoreCredit';
import StoreCreditGuest from '../components/Notices/StoreCreditGuest';

const CONTEXTS = [WC_CART, WC_CHECKOUT];

// Global Variables.
declare var wp: any;

/**
 * Notices Plugin for handling custom notices on the frontend.
 *
 * @since 4.6.0
 * @since 4.7.7 Render the BOGO deals notices instead of dispatching them, so their markup
 *              survives WooCommerce 11's block store-notice sanitizer.
 * @return {JSX.Element} The rendered notices.
 */
const Notices = () => {
  // Since the removeNotices context param can only accept a single string parameter, we need to dispatch both WC_CART and WC_CHECKOUT.
  CONTEXTS.forEach((context) => {
    dispatch(CORE_NOTICE).removeNotices(
      // The guest store credit notice is static for the whole page load. Sweeping it here
      // and recreating it on every render feeds the notices store back into this render,
      // so it is left in place and StoreCreditGuest creates it only once.
      Object.values(ID_NOTICE).filter((id) => id && id !== ID_NOTICE.ACFWF_NOTICE_STORE_CREDIT_GUEST),
      context,
    );
  });

  CartConditions();
  StoreCredit();
  StoreCreditGuest();

  // BOGODeals is a component rather than a side effect: it renders its own notices and
  // portals them into WooCommerce's notices region, so it is no longer swept by the
  // removeNotices call above and its visibility follows the Store API data instead.
  return <BOGODeals />;
};

export default function () {
  const { registerPlugin } = wp.plugins;

  registerPlugin('acfwf-notices', {
    render: Notices,
    scope: 'woocommerce-checkout',
  });
}
