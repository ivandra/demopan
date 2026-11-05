<?php

class nc_macro_functions {

    protected static array $functions = [];

    public static function add(string $name, callable $callable): void {
        if (!preg_match('/^\w+$/', $name)) {
            throw new InvalidArgumentException("Incorrect macro function name: '$name'");
        }
        self::$functions[$name] = $callable;
    }

    public static function has(string $name): bool {
        return !empty(self::$functions[$name]);
    }

    public static function reset(): void {
        self::$functions = [];
    }

    public static function evaluate_in(string $output): string {
        return self::substitute($output);
    }

    public static function remove_from(string $output): string {
        return self::substitute($output, '');
    }

    protected static function get_regexp(): string {
        $functions_regexp = implode('|', array_keys(self::$functions));
        return '/%(' . $functions_regexp . ')\(([^\)]*)\)%/';
    }

    protected static function substitute(string $output, ?string $with_string = null): string {
        $nc_core = nc_core::get_object();

        $do_not_substitute =
            empty(self::$functions) ||
            $nc_core->inside_admin ||
            strpos($output, '%') === false ||
            (
                ($nc_core->action === 'change' || $nc_core->action === 'add') &&
                $nc_core->admin_mode
            );

        if ($do_not_substitute) {
            return $output;
        }

        preg_match_all(self::get_regexp(), $output, $matches, PREG_SET_ORDER);

        if (empty($matches[0])) {
            return $output;
        }

        foreach ($matches as [$macro_string, $macro_name, $macro_arg_string]) {
            if (!self::has($macro_name)) {
                continue;
            }

            $macro_arg_string = str_replace('&#39;', "'", $macro_arg_string);

            if ($with_string === null) {
                $macro_arg_array = self::parse_arg_string($macro_arg_string);
                $macro_substitution = call_user_func_array(self::$functions[$macro_name], $macro_arg_array);
            } else {
                $macro_substitution = $with_string;
            }

            if ($macro_substitution === null) {
                continue;
            }

            $output = str_replace($macro_string, $macro_substitution, $output);
        }

        return $output;
    }

    protected static function parse_arg_string(string $str): array {
        $result = [];
        $i = 0;
        $tokens = token_get_all("<?php [$str]");

        foreach ($tokens as $token) {
            if (is_array($token)) {
                [$token_type, $value] = $token;
                switch ($token_type) {
                    case T_CONSTANT_ENCAPSED_STRING:
                        $result[$i++] = substr($value, 1, -1); // убираем кавычки
                        break;
                    case T_LNUMBER:
                    case T_DNUMBER:
                        $result[$i++] = $value;
                        break;
                    case T_STRING: // true, false, null, constants
                        if ($value === 'true') {
                            $result[$i++] = true;
                        } elseif ($value === 'false') {
                            $result[$i++] = false;
                        } elseif ($value === 'null') {
                            $result[$i++] = null;
                        } elseif (defined($value)) {
                            $result[$i++] = constant($value);
                        } else {
                            $result[$i++] = $value; // поведение как в старом PHP: неопределённая константа = строка
                        }
                        break;
                }
            }
        }

        return $result;
    }

}
