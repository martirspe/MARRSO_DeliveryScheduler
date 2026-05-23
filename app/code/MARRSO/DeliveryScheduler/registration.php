<?php
/**
 * MARRSO DeliveryScheduler Module
 *
 * Enterprise-grade Delivery Scheduling System for Magento 2
 * Supports Pickup Points and Home Delivery with advanced logistics
 *
 * @author MARRSO
 * @license OSL-3.0
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'MARRSO_DeliveryScheduler',
    __DIR__
);
