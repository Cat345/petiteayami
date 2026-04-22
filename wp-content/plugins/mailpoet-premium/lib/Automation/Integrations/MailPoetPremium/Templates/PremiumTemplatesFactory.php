<?php declare(strict_types = 1);

namespace MailPoet\Premium\Automation\Integrations\MailPoetPremium\Templates;

if (!defined('ABSPATH')) exit;


use MailPoet\Automation\Engine\Data\Automation;
use MailPoet\Automation\Engine\Data\AutomationTemplate;
use MailPoet\Automation\Engine\Data\NextStep;
use MailPoet\Automation\Engine\Templates\AutomationBuilder;
use MailPoet\Automation\Integrations\WooCommerce\WooCommerce;

class PremiumTemplatesFactory {
  /** @var AutomationBuilder */
  private $builder;

  /** @var WooCommerce */
  private $woocommerce;

  /** @var PremiumEmailFactory */
  private $emailFactory;

  public function __construct(
    AutomationBuilder $builder,
    WooCommerce $woocommerce,
    PremiumEmailFactory $emailFactory
  ) {
    $this->builder = $builder;
    $this->woocommerce = $woocommerce;
    $this->emailFactory = $emailFactory;
  }

  /** @return AutomationTemplate[] */
  public function createTemplates(): array {
    $templates = [
      $this->createSubscriberWelcomeSeriesTemplate(),
      $this->createUserWelcomeSeriesTemplate(),
    ];

    if ($this->woocommerce->isWooCommerceActive()) {
      $templates[] = $this->createThankLoyalCustomersTemplate();
      $templates[] = $this->createWinBackCustomersTemplate();
      $templates[] = $this->createAbandonedCartCampaignTemplate();
      $templates[] = $this->createAskForReviewTemplate();
      $templates[] = $this->createFollowUpPositiveReviewTemplate();
      $templates[] = $this->createFollowUpNegativeReviewTemplate();
      $templates[] = $this->createFollowUpAfterSubscriptionPurchaseTemplate();
      $templates[] = $this->createFollowUpAfterSubscriptionRenewalTemplate();
      $templates[] = $this->createFollowUpAfterFailedRenewalTemplate();
      $templates[] = $this->createFollowUpOnChurnedSubscriptionTemplate();
      $templates[] = $this->createFollowUpWhenTrialEndsTemplate();
      $templates[] = $this->createWinBackChurnedSubscribersTemplate();
    }

    return $templates;
  }

  private function createSubscriberWelcomeSeriesTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'subscriber-welcome-series',
      'welcome',
      __('Welcome series for new subscribers', 'mailpoet-premium'),
      __(
        'Welcome new subscribers and start building a relationship with them. Send an email immediately after someone subscribes to your list to introduce your brand and a follow-up two days later to keep the conversation going.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Welcome series for new subscribers', 'mailpoet-premium'),
          [
            ['key' => 'mailpoet:someone-subscribes'],
            ['key' => 'mailpoet:send-email', 'args' => ['name' => __('Welcome email', 'mailpoet-premium')]],
            ['key' => 'core:delay', 'args' => ['delay' => 2, 'delay_type' => 'DAYS']],
            ['key' => 'mailpoet:send-email', 'args' => ['name' => __('Follow-up email', 'mailpoet-premium')]],
          ],
          [
            'mailpoet:run-once-per-subscriber' => true,
          ]
        );
      },
      [
        'automationSteps' => 2, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  /**
   * This is a sample automation template which uses email templates.
   * It should be removed when we have real email templates.
   * It is not a real automation template and is not available in the UI.
   */
  public function createSampleEmailTemplateSeriesTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'sample-email-series',
      'welcome',
      __('Sample email series', 'mailpoet-premium'),
      __(
        'A sample automation template with email templates.',
        'mailpoet-premium'
      ),
      function (bool $preview = false): Automation {
        $emailId1 = $preview ? null : $this->emailFactory->createEmail([
          'subject' => __('Welcome to our newsletter!', 'mailpoet-premium'),
          'preheader' => __('Thanks for subscribing', 'mailpoet-premium'),
          'template' => 'premium-sample-1',
        ]);

        $emailId2 = $preview ? null : $this->emailFactory->createEmail([
          'subject' => __('Here’s what to expect', 'mailpoet-premium'),
          'preheader' => __('Learn more about us', 'mailpoet-premium'),
          'template' => 'premium-sample-2',
        ]);

        $emailId3 = $preview ? null : $this->emailFactory->createEmail([
          'subject' => __('A special offer just for you', 'mailpoet-premium'),
          'preheader' => __('Enjoy this discount', 'mailpoet-premium'),
          'template' => 'premium-sample-3',
        ]);

        return $this->builder->createFromSequence(
          __('Sample email series', 'mailpoet-premium'),
          [
            ['key' => 'mailpoet:someone-subscribes'],
            ['key' => 'core:delay', 'args' => ['delay' => 1, 'delay_type' => 'HOURS']],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Welcome email', 'mailpoet-premium'),
                'email_id' => $emailId1,
              ],
            ],
            ['key' => 'core:delay', 'args' => ['delay' => 2, 'delay_type' => 'DAYS']],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Introduction email', 'mailpoet-premium'),
                'email_id' => $emailId2,
              ],
            ],
            ['key' => 'core:delay', 'args' => ['delay' => 5, 'delay_type' => 'DAYS']],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Special offer email', 'mailpoet-premium'),
                'email_id' => $emailId3,
              ],
            ],
          ],
          [
            'mailpoet:run-once-per-subscriber' => true,
          ]
        );
      },
      [
        'automationSteps' => 3, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_PREMIUM
    );
  }

  private function createUserWelcomeSeriesTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'user-welcome-series',
      'welcome',
      __('Welcome series for new WordPress users', 'mailpoet-premium'),
      __(
        'Welcome new WordPress users to your site. Send an email immediately after a WordPress user registers. Send a follow-up email two days later with more in-depth information.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Welcome series for new WordPress users', 'mailpoet-premium'),
          [
            ['key' => 'mailpoet:wp-user-registered'],
            ['key' => 'mailpoet:send-email', 'args' => ['name' => __('Welcome email', 'mailpoet-premium')]],
            ['key' => 'core:delay', 'args' => ['delay' => 2, 'delay_type' => 'DAYS']],
            ['key' => 'mailpoet:send-email', 'args' => ['name' => __('Follow-up email', 'mailpoet-premium')]],
          ],
          [
            'mailpoet:run-once-per-subscriber' => true,
          ]
        );
      },
      [
        'automationSteps' => 2, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createThankLoyalCustomersTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'thank-loyal-customers',
      'purchase',
      __('Thank loyal customers', 'mailpoet-premium'),
      __(
        'These are your most important customers. Make them feel special by sending a thank you note for supporting your brand.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Thank loyal customers', 'mailpoet-premium'),
          [
            [
              'key' => 'woocommerce:order-completed',
              'filters' => [
                'operator' => 'and',
                'groups' => [
                  [
                    'operator' => 'and',
                    'filters' => [
                      [
                        'field' => 'woocommerce:customer:order-count',
                        'condition' => 'greater-than',
                        'value' => 5,
                        'params' => ['in_the_last' => ['number' => 365, 'unit' => 'days']],
                      ],
                    ],
                  ],
                ],
              ],
            ],
            ['key' => 'core:delay', 'args' => ['delay' => 1, 'delay_type' => 'DAYS']],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Thank you for your loyalty', 'mailpoet-premium'),
                'subject' => __('Thank you for your loyalty', 'mailpoet-premium'),
              ],
            ],
          ],
        );
      },
      [
        'automationSteps' => 1, // trigger and all delay steps are excluded
      ],
      // This template is available from the Basic plan, although judged solely by capabilities,
      // it would've been eligible for the Free plan. Keeping TYPE_PREMIUM allows us to exclude
      // it from the Free plan (= if tier is 0 and it's a premium template, it will be excluded).
      AutomationTemplate::TYPE_PREMIUM
    );
  }

  private function createWinBackCustomersTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'win-back-customers',
      'purchase',
      __('Win back customers', 'mailpoet-premium'),
      __(
        'Rekindle the relationship with past customers by reminding them of their favorite products and showcasing what’s new, encouraging a return to your brand.',
        'mailpoet-premium'
      ),
      function (): Automation {
        $automation = $this->builder->createFromSequence(
          __('Win back customers', 'mailpoet-premium'),
          [
            ['key' => 'woocommerce:order-completed'],
            ['key' => 'core:delay', 'args' => ['delay' => 60, 'delay_type' => 'DAYS']],
            [
              'key' => 'core:if-else',
              'filters' => [
                'operator' => 'and',
                'groups' => [
                  [
                    'operator' => 'and',
                    'filters' => [
                      [
                        'field' => 'woocommerce:customer:order-count',
                        'condition' => 'equals',
                        'value' => 0,
                        'params' => ['in_the_last' => ['number' => 60, 'unit' => 'days']],
                      ],
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('It’s been a while…', 'mailpoet-premium'),
                'subject' => __('It’s been a while…', 'mailpoet-premium'),
              ],
            ],
            ['key' => 'core:delay', 'args' => ['delay' => 15, 'delay_type' => 'DAYS']],
            [
              'key' => 'core:if-else',
              'filters' => [
                'operator' => 'and',
                'groups' => [
                  [
                    'operator' => 'and',
                    'filters' => [
                      [
                        'field' => 'woocommerce:customer:order-count',
                        'condition' => 'equals',
                        'value' => 0,
                        'params' => ['in_the_last' => ['number' => 15, 'unit' => 'days']],
                      ],
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('We’ve missed you', 'mailpoet-premium'),
                'subject' => __('We’ve missed you', 'mailpoet-premium'),
              ],
            ],
          ]
        );

        foreach ($automation->getSteps() as $step) {
          if ($step->getKey() === 'core:if-else') {
            $step->setNextSteps(array_merge($step->getNextSteps(), [new NextStep(null)]));
          }
        }
        return $automation;
      },
      [
        'automationSteps' => 4, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createAbandonedCartCampaignTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'abandoned-cart-campaign',
      'abandoned-cart',
      __('Abandoned cart campaign', 'mailpoet-premium'),
      __(
        'Encourage your potential customers to finalize their purchase when they have added items to their cart but haven’t finished the order yet. Offer a coupon code as a last resort to convert them to customers.',
        'mailpoet-premium'
      ),
      function (): Automation {
        $automation = $this->builder->createFromSequence(
          __('Abandoned cart campaign', 'mailpoet-premium'),
          [
            ['key' => 'woocommerce:abandoned-cart', 'args' => ['wait' => 60]],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('It looks like you left something behind…', 'mailpoet-premium'),
                'subject' => __('It looks like you left something behind…', 'mailpoet-premium'),
              ],
            ],
            ['key' => 'core:delay', 'args' => ['delay' => 23, 'delay_type' => 'HOURS']],
            [
              'key' => 'core:if-else',
              'filters' => [
                'operator' => 'and',
                'groups' => [
                  [
                    'operator' => 'and',
                    'filters' => [
                      [
                        'field' => 'woocommerce:customer:order-count',
                        'condition' => 'equals',
                        'value' => 0,
                        'params' => ['in_the_last' => ['number' => 1 , 'unit' => 'days']],
                      ],
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Your cart is waiting', 'mailpoet-premium'),
                'subject' => __('Your cart is waiting', 'mailpoet-premium'),
              ],
            ],
            ['key' => 'core:delay', 'args' => ['delay' => 1, 'delay_type' => 'DAYS']],
            [
              'key' => 'core:if-else',
              'filters' => [
                'operator' => 'and',
                'groups' => [
                  [
                    'operator' => 'and',
                    'filters' => [
                      [
                        'field' => 'woocommerce:customer:order-count',
                        'condition' => 'equals',
                        'value' => 0,
                        'params' => ['in_the_last' => ['number' => 1, 'unit' => 'days']],
                      ],
                      [
                        'field' => 'woocommerce:cart:cart-total',
                        'condition' => 'greater-than',
                        'value' => 100,
                      ],
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Your cart is waiting, and so is your 20% off!', 'mailpoet-premium'),
                'subject' => __('Your cart is waiting, and so is your 20% off!', 'mailpoet-premium'),
              ],
            ],
          ]
        );

        foreach ($automation->getSteps() as $step) {
          if ($step->getKey() === 'core:if-else') {
            $step->setNextSteps(array_merge($step->getNextSteps(), [new NextStep(null)]));
          }
        }
        return $automation;
      },
      [
        'automationSteps' => 5, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createAskForReviewTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'ask-for-review',
      'review',
      __('Ask to leave a review post-purchase', 'mailpoet-premium'),
      __(
        'Encourage your customers to leave a review a few days after their purchase. Show them their opinion matters.',
        'mailpoet-premium'
      ),
      function (): Automation {
        $automation = $this->builder->createFromSequence(
          __('Ask to leave a review post-purchase', 'mailpoet-premium'),
          [
            [
              'key' => 'woocommerce:order-completed',
              'filters' => [
                'operator' => 'and',
                'groups' => [
                  [
                    'operator' => 'and',
                    'filters' => [
                      [
                        'field' => 'woocommerce:customer:review-count',
                        'condition' => 'equals',
                        'value' => 0,
                      ],
                    ],
                  ],
                ],
              ],
            ],
            ['key' => 'core:delay', 'args' => ['delay' => 14, 'delay_type' => 'DAYS']],
            [
              'key' => 'core:if-else',
              'filters' => [
                'operator' => 'and',
                'groups' => [
                  [
                    'operator' => 'and',
                    'filters' => [
                      [
                        'field' => 'woocommerce:customer:review-count',
                        'condition' => 'equals',
                        'value' => 0,
                      ],
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('How was your experience?', 'mailpoet-premium'),
                'subject' => __('How was your experience?', 'mailpoet-premium'),
                'preheader' => __('We’d love your feedback', 'mailpoet-premium'),
              ],
            ],
          ]
        );

        foreach ($automation->getSteps() as $step) {
          if ($step->getKey() === 'core:if-else') {
            $step->setNextSteps(array_merge($step->getNextSteps(), [new NextStep(null)]));
          }
        }
        return $automation;
      },
      [
        'automationSteps' => 2, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createFollowUpPositiveReviewTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'follow-up-positive-review',
      'review',
      __('Follow up on a positive review (4-5 stars)', 'mailpoet-premium'),
      __(
        'Thank your happy customers for their feedback and let them know you appreciate their support.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Follow up on a positive review (4-5 stars)', 'mailpoet-premium'),
          [
            [
              'key' => 'woocommerce:made-a-review',
              'args' => [
                'rating' => [
                  'is_active' => true,
                  'from' => 4,
                  'to' => 5,
                ],
              ],
            ],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Thanks for your review!', 'mailpoet-premium'),
                'subject' => __('Thanks for your review!', 'mailpoet-premium'),
                'preheader' => __('We’re thrilled you had a good experience', 'mailpoet-premium'),
              ],
            ],
          ]
        );
      },
      [
        'automationSteps' => 1, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createFollowUpNegativeReviewTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'follow-up-negative-review',
      'review',
      __('Follow up on a negative review (1-2 stars)', 'mailpoet-premium'),
      __(
        'Reach out to unhappy customers and show you care. Offer help or gather more feedback to improve.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Follow up on a negative review (1-2 stars)', 'mailpoet-premium'),
          [
            [
              'key' => 'woocommerce:made-a-review',
              'args' => [
                'rating' => [
                  'is_active' => true,
                  'from' => 1,
                  'to' => 2,
                ],
              ],
            ],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Sorry to hear that – can we help?', 'mailpoet-premium'),
                'subject' => __('Sorry to hear that – can we help?', 'mailpoet-premium'),
                'preheader' => __('Let’s make things right', 'mailpoet-premium'),
              ],
            ],
          ]
        );
      },
      [
        'automationSteps' => 1, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createFollowUpAfterSubscriptionPurchaseTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'follow-up-after-subscription-purchase',
      'subscriptions',
      __('Follow up after a subscription purchase', 'mailpoet-premium'),
      __(
        'Thank new subscribers and let them know what to expect. A warm welcome goes a long way.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Follow up after a subscription purchase', 'mailpoet-premium'),
          [
            ['key' => 'woocommerce-subscriptions:subscription-created'],
            ['key' => 'core:delay', 'args' => ['delay' => 1, 'delay_type' => 'HOURS']],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Welcome to your subscription', 'mailpoet-premium'),
                'subject' => __('Welcome to your subscription', 'mailpoet-premium'),
                'preheader' => __('Here’s what to expect', 'mailpoet-premium'),
              ],
            ],
          ]
        );
      },
      [
        'automationSteps' => 1, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createFollowUpAfterSubscriptionRenewalTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'follow-up-after-subscription-renewal',
      'subscriptions',
      __('Follow up after a subscription renewal', 'mailpoet-premium'),
      __(
        'Reinforce the value of your subscription by reminding customers what they’re getting after every renewal.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Follow up after a subscription renewal', 'mailpoet-premium'),
          [
            ['key' => 'woocommerce-subscriptions:subscription-renewed'],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Your subscription just renewed', 'mailpoet-premium'),
                'subject' => __('Your subscription just renewed', 'mailpoet-premium'),
                'preheader' => __('Here’s a reminder of the value you get', 'mailpoet-premium'),
              ],
            ],
          ]
        );
      },
      [
        'automationSteps' => 1, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createFollowUpAfterFailedRenewalTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'follow-up-after-failed-renewal',
      'subscriptions',
      __('Follow up after failed renewal', 'mailpoet-premium'),
      __(
        'Help customers fix failed payments and continue their subscription without disruption.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Follow up after failed renewal', 'mailpoet-premium'),
          [
            ['key' => 'woocommerce-subscriptions:subscription-payment-failed'],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Trouble renewing your subscription', 'mailpoet-premium'),
                'subject' => __('Trouble renewing your subscription', 'mailpoet-premium'),
                'preheader' => __('Let’s fix this together', 'mailpoet-premium'),
              ],
            ],
          ]
        );
      },
      [
        'automationSteps' => 1, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createFollowUpOnChurnedSubscriptionTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'follow-up-on-churned-subscription',
      'subscriptions',
      __('Follow up on churned subscription', 'mailpoet-premium'),
      __(
        'Reach out to subscribers who canceled and ask for their feedback to help improve your service.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Follow up on churned subscription', 'mailpoet-premium'),
          [
            ['key' => 'woocommerce-subscriptions:subscription-expired'],
            ['key' => 'core:delay', 'args' => ['delay' => 1, 'delay_type' => 'DAYS']],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Can we ask why you left?', 'mailpoet-premium'),
                'subject' => __('Can we ask why you left?', 'mailpoet-premium'),
                'preheader' => __('Help us improve', 'mailpoet-premium'),
              ],
            ],
          ]
        );
      },
      [
        'automationSteps' => 1, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createFollowUpWhenTrialEndsTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'follow-up-when-trial-ends',
      'subscriptions',
      __('Follow up when trial ends', 'mailpoet-premium'),
      __(
        'Check in with customers after their trial ends. Encourage them to keep enjoying the benefits of their subscription.',
        'mailpoet-premium'
      ),
      function (): Automation {
        return $this->builder->createFromSequence(
          __('Follow up when trial ends', 'mailpoet-premium'),
          [
            ['key' => 'woocommerce-subscriptions:trial-ended'],
            ['key' => 'core:delay', 'args' => ['delay' => 1, 'delay_type' => 'DAYS']],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('How was your trial?', 'mailpoet-premium'),
                'subject' => __('How was your trial?', 'mailpoet-premium'),
                'preheader' => __('Let’s keep the good stuff going', 'mailpoet-premium'),
              ],
            ],
          ]
        );
      },
      [
        'automationSteps' => 1, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }

  private function createWinBackChurnedSubscribersTemplate(): AutomationTemplate {
    return new AutomationTemplate(
      'win-back-churned-subscribers',
      'subscriptions',
      __('Win back churned subscribers', 'mailpoet-premium'),
      __(
        'Re-engage former subscribers by showing what’s new and why it’s worth coming back.',
        'mailpoet-premium'
      ),
      function (): Automation {
        $automation = $this->builder->createFromSequence(
          __('Win back churned subscribers', 'mailpoet-premium'),
          [
            ['key' => 'woocommerce-subscriptions:subscription-expired'],
            ['key' => 'core:delay', 'args' => ['delay' => 30, 'delay_type' => 'DAYS']],
            [
              'key' => 'core:if-else',
              'filters' => [
                'operator' => 'and',
                'groups' => [
                  [
                    'operator' => 'and',
                    'filters' => [
                      [
                        'field' => 'woocommerce:customer:active-subscription-count',
                        'condition' => 'equals',
                        'value' => 0,
                      ],
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'mailpoet:send-email',
              'args' => [
                'name' => __('Here’s what’s new since you left', 'mailpoet-premium'),
                'subject' => __('Here’s what’s new since you left', 'mailpoet-premium'),
                'preheader' => __('We’d love to have you back', 'mailpoet-premium'),
              ],
            ],
          ]
        );

        foreach ($automation->getSteps() as $step) {
          if ($step->getKey() === 'core:if-else') {
            $step->setNextSteps(array_merge($step->getNextSteps(), [new NextStep(null)]));
          }
        }
        return $automation;
      },
      [
        'automationSteps' => 2, // trigger and all delay steps are excluded
      ],
      AutomationTemplate::TYPE_DEFAULT
    );
  }
}
