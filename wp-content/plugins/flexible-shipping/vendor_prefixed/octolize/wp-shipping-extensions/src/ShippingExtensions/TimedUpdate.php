<?php

declare (strict_types=1);
namespace FSVendor\Octolize\ShippingExtensions;

/**
 * Time-limited update displayed as unread until viewed enough times.
 */
class TimedUpdate
{
    /**
     * @var string
     */
    private $code;
    /**
     * @var DateRange
     */
    private $date_range;
    /**
     * @var int
     */
    private $required_views;
    public function __construct(string $code, DateRange $date_range, int $required_views = 1)
    {
        $this->code = $code;
        $this->date_range = $date_range;
        $this->required_views = $required_views;
    }
    public function get_code(): string
    {
        return $this->code;
    }
    public function is_active(): bool
    {
        return $this->date_range->is_active();
    }
    public function is_read(int $views): bool
    {
        return $views >= $this->required_views;
    }
}
