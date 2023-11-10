<?php

namespace atciphergroup\craftprintfulapi\helpers;

class ArrayHelper
{
    public static function countArrayDiff(array $original, array $new): int
    {
        $i = 0;
        foreach ($original as $key => $val) {
            if (!in_array($val, $new)) {
                $i++;
            }
        }

        return $i;
    }
}