<?php
require_once (getenv('ATELIER_STATE_HELPER') ?: '/usr/local/lib/atelier-state.php');
if (PHP_SAPI !== 'cli' && Atelier_State::enabled()) {
    try {
        // Serialize PHP requests so an import cannot race another reader/writer.
        Atelier_State::lock();
        $is_write = !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)
            || str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'wp-cron.php')
            || isset($_GET['cancel_order'])
            || (isset($_GET['action']) && isset($_GET['_wpnonce']));
        if ($is_write) {
            Atelier_State::sync();
            ob_start(function ($body) {
                if (empty($GLOBALS['atelier_state_save_failed'])) return $body;
                http_response_code(503);
                header_remove('Location');
                header('Retry-After: 10');
                $message = 'We could not save your changes safely. Please try again in a moment.';
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    header('Content-Type: application/json; charset=utf-8');
                    return json_encode(['success' => false, 'result' => 'failure', 'message' => $message]);
                }
                header('Content-Type: text/plain; charset=utf-8');
                return $message;
            });
        }
    } catch (Throwable $error) {
        http_response_code(503);
        header('Retry-After: 10');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'The shop is temporarily unable to save changes safely. Please try again in a moment.';
        exit;
    }
}
