<?php
namespace Nuwani\Common;
/**
 * Helper class for strings with methods behaving like the C# string-methods.
 */
class stringH
{
    /**
     * Replaces placeholders in given $stringToFormat with string from second and latter parameters.
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

    /**
     * Checks if the given $stringToCheck is null or empty.
     *
     * @param string $stringToCheck String which could be null or empty.
     *
     * @return bool If the string is null or empty.
     */
    private static function IsNullOrEmpty ($stringToCheck)
    {
        return is_null ($stringToCheck) || empty ($stringToCheck);
    }

    /**
     * Checks if the given $stringToCheck is empty or only contains spaces.
     *
     * @param string $stringToCheck String which could be empty or only contains spaces.
     *
     * @return bool If the string is empty or only contains spaces.
     */
    public static function IsNullOrWhiteSpace ($stringToCheck = null) : bool
    {
        return self :: IsNullOrEmpty ($stringToCheck) || trim ($stringToCheck) === 0;
    }
}
