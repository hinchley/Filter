<?php
trait Filter {
  protected static $_filters = [];
  protected static $_closure;

  public static function filter($method, $filter) {
    static::$_filters[$method][] =
      $filter->bindTo(null, get_called_class());
  }

  public static function hook($args, $callback, $method = null) {
    if ($method == null) {
      list(, $caller) = debug_backtrace(false);
      $method = $caller['function'];
    }

    $args += ['_method' => $method];

    if (empty(static::$_filters[$method]))
      return $callback($args);

    static::$_closure = $callback;

    $current = reset(static::$_filters[$method]);
    return $current($args);
  }

  public static function next($args) {
    $filter = next(static::$_filters[$args['_method']])
      ?: static::$_closure;
    return $filter($args);
  }

  public function __invoke($method, $args = []) {
    if (method_exists($this, $method)) {
      return static::hook($args, function($args) use ($method) {
        return call_user_func_array([$this, $method], $args);
      }, $method);
    }
  }
}