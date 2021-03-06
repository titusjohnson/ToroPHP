<?php

class Toro {
    public static function serve($routes) {
        ToroHook::fire('before_request');
        ToroLink::stash($routes);

        $request_method = strtolower($_SERVER['REQUEST_METHOD']);
        $path_info = '/';
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $path_info;
        $path_info = isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : $path_info;
        $discovered_handler = NULL;
        $regex_matches = array();

        if (isset($routes[$path_info])) {
            $discovered_handler = $routes[$path_info];
        }
        elseif ($routes) {
            $tokens = array(
                ':string' => '([a-zA-Z]+)',
                ':number' => '([0-9]+)',
                ':alpha'  => '([a-zA-Z0-9-_]+)'
            );
            foreach ($routes as $pattern => $handler_name) {                
                $pattern = strtr($pattern, $tokens);
                if (preg_match('#^/?' . $pattern . '/?$#', $path_info, $matches)) {
                    $discovered_handler = $handler_name;
                    $regex_matches = $matches;
                    break;
                }
            }
        }

        if ($discovered_handler && class_exists($discovered_handler)) {
            unset($regex_matches[0]);
            $handler_instance = new $discovered_handler();

            if (self::xhr_request() && method_exists($discovered_handler, $request_method . '_xhr')) {
                header('Content-type: application/json');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                $request_method .= '_xhr';
            }

            if (method_exists($handler_instance, $request_method)) {
                ToroHook::fire('before_handler');
                call_user_func_array(array($handler_instance, $request_method), $regex_matches);
                ToroHook::fire('after_handler');
            }
            else {
                ToroHook::fire('404');
            }
        }
        else {
            ToroHook::fire('404');
        }

        ToroHook::fire('after_request');
    }

    private static function xhr_request() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }
}

class ToroHook {
    private static $instance;

    private $hooks = array();

    private function __construct() { }
    private function __clone() { }

    public static function add($hook_name, $fn) {
        $instance = self::get_instance();
        $instance->hooks[$hook_name][] = $fn;
    }

    public static function fire($hook_name, $params = NULL) {
        $instance = self::get_instance();
        if (isset($instance->hooks[$hook_name])) {
            foreach ($instance->hooks[$hook_name] as $fn) {
                call_user_func_array($fn, array(&$params));
            }
        }
    }

    public static function get_instance() {
        if (empty(self::$instance)) {
            self::$instance = new ToroHook();
        }
        return self::$instance;
    }
}

class ToroLink {
    private $routes;
    private static $instance;

    private function __construct() {
        ToroLink::$instance = $this;
    }

    public static function stash($routes) {
        $self = new ToroLink();
        ToroLink::$instance->routes = $routes;

    }

    /**
     * Return a constructed URL path for a given method name
     * @param  string    $class_name    Name of the controler we want to link to
     * @return string                   URL string
     */
    public static function path($class_name) {
        $path = array_search($class_name, ToroLink::$instance->routes);
        $passed_params = func_get_args();
        unset($passed_params[0]);
        $tokens = array(
            '/:string/',
            '/:number/',
            '/:alpha/',
            '/:regex/'
        );

        if( ! $path) {
            throw new Exception(sprintf("No route for controller named %s found", $path));
        }

        if(count($passed_params) > 0) {
            $path = preg_replace("/\([^)]+\)/", ":regex", $path);

            foreach($passed_params as $param) {
                $path = preg_replace($tokens, $param, $path, 1);
            }
        }

        return $path;
    }
}