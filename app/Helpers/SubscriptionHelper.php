<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class SubscriptionHelper
{
    public static function getActivePlan($mfiId)
    {
        return DB::table('subscriptions')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('subscriptions.mfi_id', $mfiId)
            ->where('subscriptions.status', 'active')
            ->select(
                'subscription_plans.name as plan_name',
                'subscriptions.end_date'
            )
            ->first();
    }

    public static function isPro($mfiId)
    {
        $plan = self::getActivePlan($mfiId);

        return $plan && $plan->plan_name === 'pro';
    }

    public static function isTrial($mfiId)
    {
        $plan = self::getActivePlan($mfiId);

        return $plan && $plan->plan_name === 'trial';
    }
}
