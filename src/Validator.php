<?php
namespace Validator;

/**
 * 参数有效性检查，检查参数是否存在，值是否符合要求
 *
 * @example
 * $checker = new \Application\Libraries\Validator;
 * $checker->execute($vars, [
 *     'foo' => [                               // 通用配置
 *         'required' => (boolean),             // default true
 *         'allow_empty' => (boolean),          // default false
 *         'regexp' => (string),
 *         'eq' => (mixed),
 *         'same' => (mixed),
 *         'enum_eq' => [(mixed), ...],
 *         'enum_same' => [(mixed), ...],
 *         'callback' => function($value, array $option) {
 *             // ...
 *             return true;
 *         },
 *         'error_message' => '错误信息'
 *     ],
 *
 *     'foo' => [                               // 整数类型
 *         'type' => 'integer',
 *         'allow_negative' => (boolean),       // default true
 *     ],
 *
 *     'foo' => [                               // 浮点数类型
 *         'type' => 'float',
 *         'allow_negative' => (boolean),       // default true
 *     ],
 *
 *     'foo' => [
 *         'type' => 'ipv4',
 *     ],
 *
 *     'foo' => [
 *         'type' => 'uri',
 *     ],
 *
 *     'foo' => [
 *         'type' => 'url',
 *     ],
 *
 *     'foo' => [
 *         'type' => 'object',
 *         'instanceof' => (string),            // class name
 *     ],
 *
 *     'foo' => [
 *         'type' => 'array',                   // 普通数组
 *         'element' => [
 *             // ...
 *         ],
 *     ],
 *
 *     'foo' => [
 *         'type' => 'array',                   // hash数组
 *         'keys' => [
 *             // ...
 *         ],
 *     ],
 *
 *     'foo' => [
 *         'type' => 'json',
 *         'keys' => [
 *             // ...
 *         ],
 *     ],
 *
 *     'foo' => [
 *         'type' => 'json',
 *         'element' => [
 *             // ...
 *         ],
 *     ],
 * ]);
 */
class Validator
{
    static public $types = [
        'integer' => [
            'regexp' => '/^\-?\d+$/',
            'allow_negative' => true,
        ],
        'numeric' => [
            'regexp' => '/^\-?\d+(?:\.\d+)?$/',
            'allow_negative' => true,
        ],
        'url' => [
            'regexp' => '#^[a-z]+://[0-9a-z\-\.]+\.[0-9a-z]{1,4}(?:\d+)?(?:/[^\?]*)?(?:\?[^\#]*)?(?:\#[0-9a-z\-\_\/]*)?$#',
        ],
        'uri' => [
            'regexp' => '#^/(?:[^?]*)?(?:\?[^\#]*)?(?:\#[0-9a-z\-\_\/]*)?$#',
        ],
        'ipv4' => [
            'regexp' => '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',
        ],
        'uuid' => [
            'regexp' => '/^[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}$/i',
        ],
        'telephone' => [
            'regexp' => '/^1\d{10}$/',
        ],
        'identity' => [
            'regexp' => '/^(\d{15}$|^\d{18}$|^\d{17}(\d|X|x))$/', // '/(^[1-9]\d{7}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}$)|(^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X|x)$)/',
        ],
        'date' => [
            'regexp' => '/^(\d{4})-?(0\d{1}|1[0-2])-?(0\d{1}|[12]\d{1}|3[01])(\s+)?((0\d{1}|1\d{1}|2[0-3]):[0-5]\d{1}(:([0-5]\d{1}))?)?$/',
        ],
    ];

    protected $path = [];

    public function execute(array $values, array $options)
    {
        foreach ($options as $key => $option) {
            $option = $this->normalizeOption($option);

            if (!array_key_exists($key, $values)) {
                if ($option['required']) {
                    throw $this->exception($key, $option, 'required');
                }

                continue;
            }

            $this->check($key, $values[$key], $option);
        }

        return true;
    }

    protected function check($key, $value, array $option)
    {
        if ($value === '') {
            if ($option['allow_empty']) {
                return true;
            }

            throw $this->exception($key, $option, 'not allow empty');
        }

        switch ($option['type']) {
            case 'hash':
            case 'array':
                return $this->checkArray($key, $value, $option);
            case 'json':
                return $this->checkJson($key, $value, $option);
            case 'object':
                return $this->checkObject($key, $value, $option);
            default:
                return $this->checkLiteral($key, $value, $option);
        }
    }

    protected function checkLiteral($key, $value, array $option)
    {
        if (isset($option['same'])) {
            if ($value === $option['same']) {
                return true;
            }

            throw $this->exception($key, $option, sprintf('must strict equal [%s], current value is [%s]', $option['same'], $value));
        } elseif (isset($option['eq'])) {
            if ($value == $option['eq']) {
                return true;
            }

            throw $this->exception($key, $option, sprintf('must equal [%s], current value is [%s]', $option['eq'], $value));
        } elseif (isset($option['enum_same'])) {
            if (in_array($value, $option['enum_same'], true)) {
                return true;
            }

            throw $this->exception($key, $option, sprintf('must be strict equal one of [%s], current value is "%s"', implode(', ', $option['enum_same']), $value));
        } elseif (isset($option['enum_eq'])) {
            if (in_array($value, $option['enum_eq'])) {
                return true;
            }

            throw $this->exception($key, $option, sprintf('must be equal one of [%s], current value is "%s"', implode(', ', $option['enum_eq']), $value));
        } elseif ($callback = $option['callback']) {
            if (!call_user_func_array($callback, [$value, $option])) {
                throw $this->exception($key, $option, 'custom test failed');
            }
        } elseif ($regexp = $option['regexp']) {
            if (!preg_match($regexp, $value)) {
                throw $this->exception($key, $option, sprintf('mismatch regexp %s, current value is "%s"', $regexp, $value));
            }
        } elseif ($length = $option['min_length']) {
            if (mb_strlen($value) < $length) {
                throw $this->exception($key, $option, sprintf('must greater than $d', $length));
            }
        }

        if ($option['type'] === 'bool' || $option['type'] === 'boolean') {
            if (!is_bool($value)) {
                throw $this->exception($key, $option, sprintf('must be TRUE or FALSE, current value is "%s"', $value));
            }
        }

        if ($option['type'] === 'integer' || $option['type'] === 'numeric') {
            if ($value < 0 && !$option['allow_negative']) {
                throw $this->exception($key, $option, sprintf('not allow negative numeric, current value is "%s"', $value));
            }
        }

        if (!$option['allow_tags'] && $this->strHasTags($value)) {
            throw $this->exception($key, $option, sprintf('content not allow tags, current value is "%s"', $value));
        }

        return true;
    }

    protected function checkArray($key, $value, array $option)
    {
        if (!$value) {
            if ($option['allow_empty']) {
                return true;
            }

            throw $this->exception($key, $option, 'not allow empty');
        }

        if (!is_array($value)) {
            throw $this->exception($key, $option, 'is not array type');
        }

        if (!isset($option['keys']) && !isset($option['element'])) {
            throw $this->exception($key, $option, 'rule missing "keys" or "element"');
        }

        if (isset($option['keys']) && $option['keys']) {
            $this->path[] = $key;

            $this->execute($value, $option['keys']);

            array_pop($this->path);
        } elseif (isset($option['element']) && $option['element']) {
            $this->path[] = $key;

            foreach ($value as $element) {
                if (is_array($element) === false) {
                    $element = [$element];
                }
                $this->execute($element, $option['element']);
            }

            array_pop($this->path);
        }

        if (isset($option['callback']) && is_callable($option['callback'])) {
            if (!call_user_func_array($option['callback'], [$value, $option])) {
                throw $this->exception($key, $option, 'custom test failed');
            }
        }

        return true;
    }

    protected function checkJson($key, $value, array $option)
    {
        $value = json_decode($value, true);

        if ($value === null && ($error = json_last_error_msg())) {
            throw $this->exception($key, $option, 'json_decode() failed, ' . $error);
        }

        return $this->checkArray($key, $value, $option);
    }

    protected function checkObject($key, $value, array $option)
    {
        if (!is_object($value)) {
            throw $this->exception($key, $option, 'is not object');
        }

        if (isset($option['instanceof']) && !($value instanceof $option['instanceof'])) {
            throw $this->exception($key, $option, sprintf('must instanceof "%s"', $option['instanceof']));
        }

        return true;
    }

    private function normalizeOption(array $option)
    {
        if (isset($option['type'], self::$types[$option['type']])) {
            $option = array_merge(self::$types[$option['type']], $option);
        }

        $option = array_merge([
            'type' => null,
            'required' => true,
            'allow_empty' => false,
            'allow_tags' => false,
            'regexp' => null,
            'callback' => null,
            'min_length' => 0,
        ], $option);

        return $option;
    }

    private function exception($key, array &$option, $message)
    {
        $this->path[] = $key;
        if (array_key_exists('error_message', $option) &&
            (is_string($option['error_message']) || method_exists($option['error_message'], '__toString')))
        {
            $message = strval($option['error_message']);
        } else {
            $message = 'Key [' . implode('=>', $this->path) . '], ' . $message;
        }

        $exception = new \InvalidArgumentException($message);
        $exception->parameter = $key;

        return $exception;
    }

    protected function strHasTags($string)
    {
        return is_string($string)
        && strlen($string) > 2
        && $string !== strip_tags($string);
    }
}