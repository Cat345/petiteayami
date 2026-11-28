// Import library.
import config from '../../../acfwf-checkout-block/config.json';
import { getSetting } from '../../../shared/library/BlockIntegration';
import { dispatch, select } from '../../library/StoreAPI';
import { CORE_NOTICE, ID_NOTICE } from '../../../shared/wc-block/wc_block_notice';
import { WC_CHECKOUT } from '../../../shared/library/Context';

/**
 * Store credit guest notice.
 *
 * Tells a logged out customer that store credits are available once they log in. The
 * server decides whether the notice shows, so this only renders what it is given.
 *
 * @since 4.7.7
 */
export default function () {
  // Get the settings from the integration interface.
  const { store_credits } = getSetting(config.integration);

  if (!store_credits) return null;

  const { show_guest_notice, guest_notice_text } = store_credits;

  if (!show_guest_notice || !guest_notice_text) return null;

  // Create the notice once. The condition here is constant for the whole page load, so
  // dispatching on every render would feed the notices store back into this render and
  // spin. NoticesPlugin deliberately leaves this id out of its removeNotices sweep.
  const alreadyShown = select(CORE_NOTICE)
    .getNotices(WC_CHECKOUT)
    .some((notice: { id: string }) => notice.id === ID_NOTICE.ACFWF_NOTICE_STORE_CREDIT_GUEST);

  if (alreadyShown) return null;

  // Dispatch outside the render phase. Creating the notice inline would be a setState
  // during render, which React warns about. StoreCredit defers the same way.
  setTimeout(() => {
    dispatch(CORE_NOTICE).createNotice('info', guest_notice_text, {
      context: WC_CHECKOUT,
      id: ID_NOTICE.ACFWF_NOTICE_STORE_CREDIT_GUEST,
      isDismissible: false,
    });
  }, 0);

  return null;
}
