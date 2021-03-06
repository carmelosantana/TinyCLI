<?php

declare(strict_types=1);

namespace carmelosantana\TinyCLI;

use jc21\CliTable;
use jc21\CliTableManipulator;
use Wujunze\Colors;

class TinyCLI
{

    /**
     * Output
     */
    public static function echo($msg = null, $args = [])
    {
        $def = array(
            // progress
            'done' => null,
            'progress' => false,

            // outputs
            'bg' => null,
            'fg' => null,
            'color_scheme' => null,
            'format' => false,
            'header' => null,
            'function' => null,
            'show_time' => false,

            // actions
            'echo' => true,
            'exit' => false,
            'write' => true,
            'stdin' => [],
            'debug' => TinyCLI::get_arg('debug'),
        );

        // args to vars
        $args = array_merge($def, $args);
        extract($args);

        // vars
        $out = $time = null;

        // setup
        if ($show_time and !$done)
            $time = date('h:i:s A') . ' - ';

        // header 
        if ($progress)
            $header = 'work';

        if ($header) {
            switch ($header) {
                case 'error':
                    if (is_string($function))
                        $msg = $function . ' ' . $msg;
                    break;
            }
        }
        $args['color_scheme'] = $header;

        // color + output
        if (($msg or $header) and !$done) {
            switch ($args['header']) {
                case 'error':
                    $out = self::_echo_padding($msg, $args);
                    break;

                case 'debug':
                    if (!$debug)
                        return false;

                default:
                    $out = $time . self::text_style(($header ? self::_echo_header($header) : ''), $args + ['style' => 'bold']) . self::text_style($msg, $args);
                    break;
            }
        }

        // append finish
        if ($done) {
            $out .= '] ';
            $out .= $msg ? $msg : '100%';
        }

        // leave open for progress
        $out .= $progress ? ' [' : PHP_EOL;

        // echo if not suppressed
        if ($echo and defined('STDOUT'))
            fwrite(STDOUT, $out);

        // https://stackoverflow.com/questions/6543841/php-cli-getting-input-from-user-and-then-dumping-into-variable-possible#6543936
        if ($stdin) {
            $handle = fopen(STDIN, 'r');
            $line = fgets($handle);
            return escapeshellcmd(trim($line));
        }

        // cya buddy
        if ($exit or $header == 'fatal_error')
            die(PHP_EOL);

        // just return it
        return $out;
    }

    public static function echoDebug($msg = null, $args = [])
    {
        self::echo($msg, array_merge(['header' => ($args['header'] ?? 'debug')], $args));
    }

    public static function echoArray($schema = null, $data = null, $args = [])
    {
        $def = array(
            // options
            'header' => false,
            'footer' => false,
            'after_item' => false,
            'multi_line' => false,
            'echo' => true,

            // vars
            'out' => null,
        );

        // args to vars
        $args = array_merge($def, $args);
        extract($args);

        if ($schema) {
            $bar = ' +';
            $out .= ' | ';

            foreach ($schema as $key => $meta) {
                $bar .= self::str_pad_unicode('', $meta['size'] + 2, '-') . '+';
                $out .= self::text_style(str_pad($meta['title'], $meta['size']), ['style' => 'bold']) . ' | ';
            }
            $out .= PHP_EOL;
            $bar .= PHP_EOL;
        }

        if ($data) {
            $out = null;
            if (!$multi_line)
                $out .= ' | ';

            foreach ($schema as $key => $value) {
                // skip if not in schema
                if (!isset($data[$key]))
                    continue;

                if ($multi_line) {
                    foreach (self::explode_on_rn($data[$key]) as $line)
                        $out .= ' | ' . self::str_pad_unicode($line, $schema[$key]['size']) . ' | ' . PHP_EOL;
                } else {
                    $out .= self::str_pad_unicode($data[$key], $schema[$key]['size']) . ' | ';
                }
            }

            if (!$multi_line)
                $out .= PHP_EOL;

            if ($after_item)
                $out .= $bar;
        }

        // build outputs
        if ($header) {
            $output = $bar . $out . $bar;
        } elseif ($footer) {
            $output = $bar;
        } else {
            $output = $out;
        }

        // echo or return
        if ($echo)
            echo $output;

        return $output;
    }

    public static function table($data = [], $schema = [])
    {
        if (empty($data) or !self::isCLI())
            return false;

        $table = new CliTable;
        $table->setTableColor('blue');
        $table->setHeaderColor('cyan');
        foreach ($schema as $tbl_schema)
            $table->addField($tbl_schema[0], $tbl_schema[1], ($tbl_schema[2] ? new CliTableManipulator($tbl_schema[2]) : false), $tbl_schema[3]);
        $table->injectData($data);
        $table->display();
    }

    public static function cli_echo_footer(string $finished = 'Finished.', bool $echo = true)
    {
        $out = PHP_EOL;
        $out .= self::echo($finished, ['fg' => 'green', 'style' => 'bold', 'echo' => false]);
        $out .= self::echo(self::format_bytes(memory_get_peak_usage()), ['header' => 'Peak memory', 'fg' => 'light_gray', 'echo' => false]);

        if ($echo)
            echo $out;

        return $out;
    }

    public static function madeWithLove(string $where = '', string $made_with = 'Made with ', string $emoji = '???', bool $echo = true)
    {
        $where = !empty($where) ? ' in ' . $where : $where;

        $out = PHP_EOL;
        $out .= self::echoArray(
            // schema
            array(
                'love' => array(
                    'title' =>
                    self::text_style($made_with, ['fg' => 'cyan', 'style' => 'bold']) .
                        self::text_style($emoji, ['fg' => 'red', 'style' => 'bold']) .
                        self::text_style($where, ['fg' => 'cyan', 'style' => 'bold']),
                    'size' => (mb_strlen($made_with) + mb_strlen($emoji) + mb_strlen($where)),
                )
            ),

            // data
            false,

            // args
            array(
                'header' => true,
                'echo' => false,
            )
        );
        $out .= PHP_EOL;

        if ($echo)
            echo $out;

        return $out;
    }

    /**
     * Styles
     */
    public static function text_style(string $txt, array $args)
    {
        if (!self::isCLI())
            return $txt;

        $def = [
            'bg' => null,
            'fg' => null,
            'color_scheme' => null,
            'style' => null,
        ];

        // args to vars
        $args = extract(array_merge($def, $args));

        if (!$txt)
            return false;

        /**
         *  Foreground Colors            Background Colors
         *
         *  - black                      * light_gray
         *  - dark_gray                  
         *  - blue
         *  - light_blue
         *  - green
         *  - light_green
         *  - cyan
         *  - light_cyan
         *  - red
         *  - light_red
         *  - purple
         *  - light_purple
         *  - brown
         *  - yellow
         *  - light_gray
         *  - white
         */
        switch ($color_scheme) {
            case 'debug':
                $fg = 'black';
                $bg = 'yellow';
                break;

            case 'error':
                $bg = 'red';
                break;

            case 'warning':
                $bg = 'light_red';
                break;
        }

        // style
        switch ($style) {
            case 'bold':
                $txt = "\033[1m" . $txt . "\033[0m";
                break;
        }

        // color
        if ($bg or $fg) {
            $colors = new Colors();
            $txt = $colors->getColoredString($txt, $fg, $bg);
        }

        return $txt;
    }

    public static function _echo_padding(string $msg, array $args)
    {
        $out = null;
        $msg .= '  ';
        $text = '  ' . $args['header'] . ': ' . $msg;
        $text_array = [
            self::text_style(self::str_pad_unicode(' ', strlen($text)), $args),
            self::text_style('  ' . self::_echo_header($args['header']), $args + ['style' => 'bold']) . self::text_style($msg, $args),
            self::text_style(self::str_pad_unicode(' ', strlen($text)), $args),
        ];
        foreach ($text_array as $txt)
            $out .= '  ' . $txt . PHP_EOL;
        return PHP_EOL . $out;
    }

    public static function _echo_header(string $header)
    {
        return strtoupper($header) . ': ';
    }

    /**
     * $_GET
     */
    public static function get_arg(string $a, $alt = false)
    {
        if (isset($_GET[$a])) {
            switch (strtolower((string) $_GET[$a])) {
                case '0':
                case 'false':
                    return false;

                case '1':
                case 'true':
                    return true;
            }
            return $_GET[$a];
        }

        return $alt;
    }

    public static function arguments(): void
    {
        global $args, $argv;

        // browser check
        if (!isset($_SERVER) or (isset($_SERVER) and !isset($_SERVER['HTTP_USER_AGENT'])))
            $args = $argv;

        if (isset($args))
            array_shift($args);

        // Written by Colin Fein
        if (!empty($args)) {
            foreach ($args as $param) {
                if (strpos($param, '--') === 0) {
                    $paramString = substr($param, 2);
                    if (!empty($paramString)) {
                        if (strpos($paramString, '=') !== false) {
                            list($key, $value) = explode('=', $paramString);
                            $_GET[strtolower($key)] = $value;
                        } else {
                            $_GET[strtolower($paramString)] = null;
                        }
                    }
                }
            }
        }
    }

    /**
     * https://developer.wordpress.org/reference/functions/wp_parseArgs/
     *
     * @param array $args
     * @return void
     */
    public static function parseArgs($args = [], $defaults = [])
    {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            parse_str($args, $parsed_args);
        }

        if (is_array($defaults) && $defaults) {
            return array_merge($defaults, $parsed_args);
        }
        return $parsed_args;
    }

    /**
     * Conditions
     */
    public static function isCLI()
    {
        if (php_sapi_name() == "cli")
            return true;

        return false;
    }

    public static function is_dot_file($file = null)
    {
        return basename($file)[0] == '.';
    }

    /**
     * Helpers
     */
    // explodes on new line
    public static function explode_on_rn($str = null)
    {
        return explode(',', str_replace(array("\r\n", "\r", "\n"), ',', $str));
    }

    // converts bytes to KB, MB, GB, TB
    public static function format_bytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        // $bytes /= pow(1024, $pow);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // http://php.net/manual/en/function.next.php
    public static function has_next(array $_array)
    {
        return next($_array) !== false ?: key($_array) !== null;
    }

    public static function lr_trim($string = null)
    {
        // trim both left + right of extra non-alpha characters
        return ltrim(rtrim(trim($string), '$-_.+!*\'(),{}|\\^~[]`<>#%";/?:@&='), '$-_.+!*\'(),{}|\\^~[]`<>#%";/?:@&=');
    }

    // https://secure.php.net/manual/en/function.str-pad.php#111147
    public static function str_pad_unicode($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT)
    {
        $str_len = mb_strlen($str);
        $pad_str_len = mb_strlen($pad_str);
        if (!$str_len && ($dir == STR_PAD_RIGHT || $dir == STR_PAD_LEFT)) {
            $str_len = 1; // @debug
        }
        if (!$pad_len || !$pad_str_len || $pad_len <= $str_len) {
            return $str;
        }

        $result = null;
        $repeat = ceil($str_len - $pad_str_len + $pad_len);
        if ($dir == STR_PAD_RIGHT) {
            $result = $str . str_repeat($pad_str, (int) round($repeat));
            $result = mb_substr($result, 0, $pad_len);
        } else if ($dir == STR_PAD_LEFT) {
            $result = str_repeat($pad_str, $repeat) . $str;
            $result = mb_substr($result, -$pad_len);
        } else if ($dir == STR_PAD_BOTH) {
            $length = ($pad_len - $str_len) / 2;
            $repeat = ceil($length / $pad_str_len);
            $result = mb_substr(str_repeat($pad_str, $repeat), 0, floor($length))
                . $str
                . mb_substr(str_repeat($pad_str, $repeat), 0, ceil($length));
        }

        return $result;
    }

    // https://www.php.net/manual/en/function.is-bool.php
    /**
     * Check "Booleanic" Conditions :)
     *
     * @param  [mixed]  $variable  Can be anything (string, bol, integer, etc.)
     * @return [boolean]           Returns TRUE  for "1", "true", "on" and "yes"
     *                             Returns FALSE for "0", "false", "off" and "no"
     *                             Returns NULL otherwise.
     */
    public static function is_enabled($variable)
    {
        if (!isset($variable))
            return null;

        return filter_var($variable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
