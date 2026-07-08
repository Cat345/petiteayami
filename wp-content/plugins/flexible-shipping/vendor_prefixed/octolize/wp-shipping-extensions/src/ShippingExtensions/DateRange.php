<?php

declare (strict_types=1);
namespace FSVendor\Octolize\ShippingExtensions;

/**
 * Date range checked against WordPress current time.
 */
class DateRange
{
    /**
     * @var string
     */
    private $start_date;
    /**
     * @var string
     */
    private $end_date;
    public function __construct(string $start_date, string $end_date)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }
    public function is_active(): bool
    {
        $start_date = strtotime($this->start_date);
        $end_date = strtotime($this->end_date);
        return current_time('timestamp') < $end_date && current_time('timestamp') > $start_date;
    }
}
