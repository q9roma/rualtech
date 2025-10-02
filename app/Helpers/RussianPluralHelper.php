<?php

if (!function_exists('pluralize_russian')) {
    /**
     * Склонение русских слов в зависимости от числа
     * 
     * @param int $count
     * @param array $forms [единственное число, множественное 2-4, множественное 5+]
     * @return string
     */
    function pluralize_russian($count, $forms) {
        $count = abs($count);
        
        if ($count % 10 == 1 && $count % 100 != 11) {
            return $forms[0]; // 1, 21, 31, но не 11
        } elseif (in_array($count % 10, [2, 3, 4]) && !in_array($count % 100, [12, 13, 14])) {
            return $forms[1]; // 2, 3, 4, 22, 23, 24, но не 12, 13, 14
        } else {
            return $forms[2]; // 0, 5-20, 25-30, и т.д.
        }
    }
}

if (!function_exists('products_count_text')) {
    /**
     * Возвращает текст для количества продуктов
     * 
     * @param int $count
     * @return string
     */
    function products_count_text($count) {
        if ($count <= 0) {
            return '';
        }
        
        $word = pluralize_russian($count, ['продукт', 'продукта', 'продуктов']);
        return "{$count} {$word}";
    }
}
