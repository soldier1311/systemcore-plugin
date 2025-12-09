<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore Security Middleware
 * حماية مركزية لجميع طلبات AJAX
 * - Nonce
 * - User Capabilities
 * - Sanitization
 * - Safe JSON Response
 */

class SystemCore_Security {

    /**
     * التحقق الأساسي – يتم استدعاؤه داخل كل ملف AJAX
     */
    public static function verify($capability = 'manage_options')
    {
        // 1) منع الوصول المباشر لغير POST أو AJAX
        if (!self::is_ajax_request()) {
            self::deny('Invalid access.');
        }

        // 2) التحقق من nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'systemcore_admin_nonce')) {
            self::deny('Security check failed (nonce).');
        }

        // 3) التحقق من صلاحيات المستخدم
        if (!current_user_can($capability)) {
            self::deny('Unauthorized user.');
        }

        return true;
    }


    /**
     * التحقق أن الطلب فعلاً AJAX
     */
    private static function is_ajax_request()
    {
        return (
            defined('DOING_AJAX') && DOING_AJAX ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
             strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        );
    }


    /**
     * sanitization عام للحقول
     */
    public static function clean($data)
    {
        if (is_array($data)) {
            return array_map([__CLASS__, 'clean'], $data);
        }

        if (is_numeric($data)) {
            return intval($data);
        }

        return sanitize_text_field($data);
    }


    /**
     * sanitization خاص بالروابط
     */
    public static function clean_url($url)
    {
        return esc_url_raw($url);
    }


    /**
     * رفض الطلب وإرجاع JSON موحد
     */
    public static function deny($message = 'Access denied.')
    {
        wp_send_json_error([
            'success' => false,
            'message' => $message,
        ], 403);
    }
}
