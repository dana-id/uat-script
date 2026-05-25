<?php

namespace DanaUat\Disbursement;

use DanaUat\Helper\MerchantBniVaTopUp;
use PHPUnit\Framework\TestCase;

abstract class AbstractDisbursementTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        MerchantBniVaTopUp::ensure();
    }
}
