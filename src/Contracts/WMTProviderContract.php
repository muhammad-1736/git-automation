<?php

namespace Mu\CherryPicker\Contracts;

/**
 * WMT (Work Management Tool) Provider Contract
 */
interface WMTProviderContract
{
    public function getSummary(string $ticketNumber): string;

    public function getFeatureOrBug(string $ticketNumber): string;

    public function getFixVersion(string $ticketNumber): string;
}
