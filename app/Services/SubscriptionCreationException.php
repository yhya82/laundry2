<?php

namespace App\Services;

use DomainException;

/**
 * A subscription couldn't be created for a business reason. The message is
 * safe to show staff; $kind lets a caller react differently (e.g. send them
 * to the customer page to settle a balance).
 */
class SubscriptionCreationException extends DomainException
{
    public const SIGNUPS_DISABLED = 'signups_disabled';

    public const NOT_SUBSCRIPTION_CUSTOMER = 'not_subscription_customer';

    public const OUTSTANDING_BALANCE = 'outstanding_balance';

    public const ALREADY_SUBSCRIBED = 'already_subscribed';

    public function __construct(string $message, public readonly string $kind)
    {
        parent::__construct($message);
    }
}
