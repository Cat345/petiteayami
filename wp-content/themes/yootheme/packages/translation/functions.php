<?php

namespace YOOtheme;

/**
 * Translates the given message.
 *
 * @param array<string, string>  $parameters
 */
function trans(string $id, array $parameters = [], ?string $locale = null): string
{
    $app = Application::getInstance();

    return $app(Translator::class)->trans($id, $parameters, $locale);
}

/**
 * Translates the given choice message by choosing a translation according to a number.
 *
 * @param array<string, string>       $parameters An array of parameters for the message
 */
function transChoice(
    string $id,
    int $number,
    array $parameters = [],
    ?string $locale = null
): string {
    $app = Application::getInstance();

    return $app(Translator::class)->trans($id, $number, $parameters, $locale);
}
