<?php

declare(strict_types=1);

return [
    'promotion' => [
        'discount'          => 'Discount',
        'credit_from_order' => 'Wallet credit from :promotion',
        'gift_from_order'   => 'Gift credit from :promotion',
    ],

    'gift_expiry_reclaimed' => 'Gift balance reclaimed after expiry',

    'campaign' => [
        'gift_allocated'  => 'Gift credit of :amount has been allocated to your wallet from :campaign',
        'bonus_processed' => 'Bonus of :amount has been added to your wallet from :campaign for :event',
        'manual_trigger'  => 'manual allocation',

        'threshold_descriptions' => [
            'lifetime' => 'Threshold measured across all history',
            'windowed' => 'Threshold measured within the campaign dates',
        ],

        'descriptions' => [
            'registration_bonus' => 'Bonus awarded to new users upon registration',
            'birthday_gift'      => 'Special gift credit given on user birthdays',
            'referral_bonus'     => 'Reward for successful user referrals',
            'loyalty_reward'     => 'Reward for loyal customers based on activity',
            'seasonal_bonus'     => 'Special seasonal promotions and bonuses',
            'milestone_reward'   => 'Achievement-based milestone rewards',
            'manual_allocation'  => 'Manually allocated credits by administrators',
        ],
    ],
];
