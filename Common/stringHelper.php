<?php
namespace Nuwani\Common;
/**
 * Helper class to support C#-like string.Format.
 */
class stringHelper
{
    /**
     * Replaces placeholders in given $stringToFormat with given strings.
     *
     * @param string $stringToFormat String with placeholders to replace.
     * @param string $placeHolders One or multiple strings to replace for the placeholder.
     *
     * @return string $stringToFormat with replaced placeholders.
     */
    public static function Format (string $stringToFormat, $placeHolders)
    {
        foreach (func_get_args () as $sKey => $sValue)
        {
            $sKey = $sKey - 1;
            if ($sKey == -1)
                continue;

            $stringToFormat = str_replace ('{' .$sKey  . '}', $sValue, $stringToFormat);
        }

        return $stringToFormat;
    }
}