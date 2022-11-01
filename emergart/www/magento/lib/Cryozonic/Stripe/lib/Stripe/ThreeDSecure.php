<?php

class Stripe_ThreeDSecure extends Stripe_ApiResource
{
    /**
     * @return string The endpoint URL for the given class.
     */
    public static function classUrl($class)
    {
        return "/v1/3d_secure";
    }

    /**
     * @param array|null $params
     * @param array|string|null $opts
     *
     * @return ThreeDSecure The created 3D Secure object.
     */
    public static function create($params = null, $opts = null)
    {
        $class = get_class();
        return self::_scopedCreate($class, $params, $opts);
    }
}