<?php

namespace App\Support;

final class CatalogName
{
    public static function key(string $name): string
    {
        return strtr(preg_replace('/\p{Mn}+/u', '', mb_strtolower(trim($name))), array_combine(
            preg_split('//u', 'áéíóúüñàèìòùâêîôûäëïöÿç', -1, PREG_SPLIT_NO_EMPTY),
            str_split('aeiouunaeiouaeiouaeioyc'),
        ));
    }
}
