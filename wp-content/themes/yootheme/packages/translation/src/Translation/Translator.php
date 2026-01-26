<?php

namespace YOOtheme\Translation;

use YOOtheme\Translator as TranslatorInterface;

class Translator implements TranslatorInterface
{
    /**
     * @var string
     */
    protected string $locale;

    /**
     * @var array<string, array<string, string>>
     */
    protected array $resources = [];

    /**
     * @inheritdoc
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @inheritdoc
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Gets a Resource.
     *
     * @return array<string, string>
     */
    public function getResource(?string $locale = null)
    {
        if ($locale === null) {
            $locale = $this->getLocale();
        }

        return $this->resources[$locale] ?? [];
    }

    /**
     * @inheritdoc
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * @inheritdoc
     */
    public function addResource($resource, ?string $locale = null): self
    {
        if ($locale === null) {
            $locale = $this->getLocale();
        }

        if (is_string($resource) && is_file($resource)) {
            $resource = json_decode(file_get_contents($resource), true);
        } else {
            $resource = [];
        }

        $this->resources[$locale] = isset($this->resources[$locale])
            ? array_replace($this->resources[$locale], $resource)
            : $resource;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function trans(string $id, array $parameters = [], ?string $locale = null): string
    {
        if ($locale === null) {
            $locale = $this->getLocale();
        }

        if (isset($this->resources[$locale][$id])) {
            return strtr($this->resources[$locale][$id], $parameters);
        }
        return strtr($id, $parameters);
    }

    /**
     * @inheritdoc
     */
    public function transChoice(
        string $id,
        int $number,
        array $parameters = [],
        ?string $locale = null
    ): string {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        return strtr($this->choose($this->trans($id, [], $locale), $number, $locale), $parameters);
    }

    /**
     * Returns the correct portion of the message based on the given number.
     *
     * @throws \InvalidArgumentException
     */
    public function choose(string $message, int $number, string $locale): string
    {
        $parts = explode('|', $message);
        $explicitRules = [];
        $standardRules = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (
                preg_match(
                    '/^(?P<interval>' . Interval::getIntervalRegexp() . ')\s*(?P<message>.*?)$/x',
                    $part,
                    $matches,
                )
            ) {
                $explicitRules[$matches['interval']] = $matches['message'];
            } elseif (preg_match('/^\w+\:\s*(.*?)$/', $part, $matches)) {
                $standardRules[] = $matches[1];
            } else {
                $standardRules[] = $part;
            }
        }
        // try to match an explicit rule, then fallback to the standard ones
        foreach ($explicitRules as $interval => $m) {
            if (Interval::test($number, $interval)) {
                return $m;
            }
        }
        $position = PluralizationRules::get($number, $locale);
        if (!isset($standardRules[$position])) {
            // when there's exactly one rule given, and that rule is a standard
            // rule, use this rule
            if (1 === count($parts) && isset($standardRules[0])) {
                return $standardRules[0];
            }
            throw new \InvalidArgumentException(
                sprintf(
                    'Unable to choose a translation for "%s" with locale "%s" for value "%d". Double check that this translation has the correct plural options (e.g. "There is one apple|There are %%count%% apples").',
                    $message,
                    $locale,
                    $number,
                ),
            );
        }
        return $standardRules[$position];
    }
}
