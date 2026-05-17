<?php
if (!defined('ABSPATH')) {
    exit;
}

$mulopimfwc_build_channel_file = __DIR__ . '/build-channel.php';
if (!defined('MULOPIMFWC_RELEASE_CHANNEL') && is_readable($mulopimfwc_build_channel_file)) {
    require_once $mulopimfwc_build_channel_file;
}
unset($mulopimfwc_build_channel_file);

if (!defined('MULOPIMFWC_RELEASE_CHANNEL')) {
    define('MULOPIMFWC_RELEASE_CHANNEL', 'edd');
}

function mulopimfwc_get_release_channel()
{
    $channel = strtolower((string) MULOPIMFWC_RELEASE_CHANNEL);
    return in_array($channel, ['edd', 'envato'], true) ? $channel : 'edd';
}

function mulopimfwc_is_edd_build()
{
    return mulopimfwc_get_release_channel() === 'edd';
}

function mulopimfwc_is_envato_build()
{
    return mulopimfwc_get_release_channel() === 'envato';
}

function mulopimfwc_get_support_url()
{
    $default_url = mulopimfwc_is_envato_build()
        ? 'https://codecanyon.net/user/plugincypro'
        : 'https://plugincy.com/support';

    return apply_filters('mulopimfwc_support_url', $default_url, mulopimfwc_get_release_channel());
}

function mulopimfwc_get_envato_downloads_url()
{
    return apply_filters('mulopimfwc_envato_downloads_url', 'https://codecanyon.net/downloads');
}

if (mulopimfwc_is_envato_build()) {
    if (!function_exists('mulopimfwc_is_license_valid')) {
        function mulopimfwc_is_license_valid()
        {
            return true;
        }
    }

    if (!function_exists('mulopimfwc_premium_feature')) {
        function mulopimfwc_premium_feature()
        {
            return true;
        }
    }

    if (!function_exists('mulopimfwc_get_pro_class')) {
        function mulopimfwc_get_pro_class($blur = true, $selector = '', $not_licenced = 'mulopimfwc_pro_only')
        {
            return $selector;
        }
    }
}
