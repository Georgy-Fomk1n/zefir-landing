<?php

if (! function_exists('rub')) {
    /** 1 050 ₽ с неразрывными пробелами */
    function rub(int $n): string
    {
        return number_format($n, 0, '', "\u{00A0}") . "\u{00A0}₽";
    }
}
