<?php

namespace FSVendor\WPDesk\Plugin\Flow\Initialization\Simple;

use FSVendor\WPDesk\Plugin\Flow\Initialization\InitializationFactory;
use FSVendor\WPDesk\Plugin\Flow\Initialization\InitializationStrategy;
/**
 * Can decide if strategy is for free plugin or paid plugin
 */
class SimpleFactory implements InitializationFactory
{
    /** @var bool */
    private $free;
    /** @var bool */
    private $tracker_enabled;
    /**
     * @param bool $free True for free/repository plugin
     * @param bool $tracker_enabled True when tracker should be initialized
     */
    public function __construct($free = \false, $tracker_enabled = \true)
    {
        $this->free = $free;
        $this->tracker_enabled = $tracker_enabled;
    }
    /**
     * Create strategy according to the given flag
     *
     * @param \WPDesk_Plugin_Info $info
     *
     * @return InitializationStrategy
     */
    public function create_initialization_strategy(\FSVendor\WPDesk_Plugin_Info $info)
    {
        if ($this->free) {
            return new SimpleFreeStrategy($info, $this->tracker_enabled);
        }
        return new SimplePaidStrategy($info);
    }
}
