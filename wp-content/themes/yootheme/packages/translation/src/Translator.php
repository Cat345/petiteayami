<?php

namespace YOOtheme;

interface Translator
{
    /**
     * Returns the current locale.
     */
    public function getLocale(): string;

    /**
     * Sets the current locale.
     */
    public function setLocale(string $locale): void;

    /**
     * Gets all Resources.
     *
     * @return array<string, array<string, string>>
     */
    public function getResources();

    /**
     * Adds a Resource.
     *
     * @param string|array<string, string>  $resource
     */
    public function addResource($resource, ?string $locale = null): self;

    /**
     * Translates the given message.
     *
     * @param array<string, string>  $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $locale = null): string;

    /**
     * Translates the given choice message by choosing a translation according to a number.
     *
     * @param array<string, string> $parameters An array of parameters for the message
     */
    public function transChoice(
        string $id,
        int $number,
        array $parameters = [],
        ?string $locale = null
    ): string;
}
