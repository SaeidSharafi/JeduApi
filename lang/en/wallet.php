<?php

declare(strict_types=1);

return [
    'promotion' => [
        'discount'          => 'Discount',
        'credit_from_order' => 'Wallet credit from :promotion',
        'gift_from_order'   => 'Gift credit from :promotion',
    ],

    'campaign' => [
        'gift_allocated'  => 'Gift credit of :amount has been allocated to your wallet from :campaign',
        'bonus_processed' => 'Bonus of :amount has been added to your wallet from :campaign for :event',
        'manual_trigger'  => 'manual allocation',

        'types' => [
            'registration_bonus' => 'Registration Bonus',
            'birthday_gift'      => 'Birthday Gift',
            'referral_bonus'     => 'Referral Bonus',
            'welcome_gift'       => 'Welcome Gift',
            'loyalty_reward'     => 'Loyalty Reward',
            'seasonal_bonus'     => 'Seasonal Bonus',
            'milestone_reward'   => 'Milestone Reward',
            'manual_allocation'  => 'Manual Allocation',
        ],

        'descriptions' => [
            'registration_bonus' => 'Bonus awarded to new users upon registration',
            'birthday_gift'      => 'Special gift credit given on user birthdays',
            'referral_bonus'     => 'Reward for successful user referrals',
            'welcome_gift'       => 'Welcome credit for first-time users',
            'loyalty_reward'     => 'Reward for loyal customers based on activity',
            'seasonal_bonus'     => 'Special seasonal promotions and bonuses',
            'milestone_reward'   => 'Achievement-based milestone rewards',
            'manual_allocation'  => 'Manually allocated credits by administrators',
        ],
    ],
];
