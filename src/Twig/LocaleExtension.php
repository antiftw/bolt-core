<?php

declare(strict_types=1);

namespace Bolt\Twig;

use Bolt\Configuration\Config;
use Bolt\Utils\LocaleHelper;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Symfony\Contracts\Translation\TranslatorInterface;
use Illuminate\Support\Collection;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class LocaleExtension extends AbstractExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LocaleHelper $localeHelper,
        private readonly Config $config,
        private readonly Environment $twig,
        private readonly string $defaultLocale
    ){}

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        $safe = [
            'is_safe' => ['html'],
        ];

        return [
            new TwigFilter('localdate', [$this, 'localdate'], $safe),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        $safe = [
            'is_safe' => ['html'],
        ];
        $env = ['needs_environment' => true];

        return [
            new TwigFunction('__', [$this, 'translate'], $safe),
            new TwigFunction('htmllang', [$this, 'getHtmlLang'], $env),
            new TwigFunction('locales', [$this, 'getLocales'], $env),
            new TwigFunction('locale', [$this, 'getLocale']),
            new TwigFunction('flag', [$this, 'flag'], $safe),
        ];
    }

    public function getHtmlLang(Environment $twig): string
    {
        $current = $this->localeHelper->getCurrentLocale($twig);

        if ($current) {
            return $current->get('code') ?? 'en';
        }

        return 'en';
    }

    public function translate(string $id, array $parameters = [], $domain = null, $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(Collection|string $localeCode): Collection
    {
        return $this->localeHelper->localeInfo($localeCode);
    }

    /**
     * Takes the list of codes of the locales (languages) enabled in the
     * application and returns an array with the name of each locale written
     * in its own language (e.g. English, Français, Español, etc.).
     */
    public function getLocales(Environment $twig, ?Collection $localeCodes = null, bool $all = false): Collection
    {
        return $this->localeHelper->getLocales($twig, $localeCodes, $all);
    }

    public function flag(Collection|string $localeCode): string
    {
        $locale = $this->localeHelper->localeInfo($localeCode);

        return sprintf(
            '<span class="fp mr-1 %s" title="%s - %s / %s"></span>',
            $locale->get('flag'),
            $locale->get('name'),
            $locale->get('localizedname'),
            $locale->get('code')
        );
    }

    public function localdate($dateTime, ?string $format = null, ?string $locale = null, ?string $timezone = null): string
    {
        if ($dateTime instanceof \Datetime) {
            $dateTime = Carbon::createFromTimestamp($dateTime->getTimestamp(), $dateTime->getTimezone());
        } elseif (empty($dateTime)) {
            $dateTime = Carbon::now();
        } else {
            $dateTime = Carbon::parse($dateTime);
        }

        if ($format === null) {
            $format = $this->config->get('general/date_format');
        }

        if ($locale === null) {
            $current = $this->getHtmlLang($this->twig);
            $locale = ! empty($current) ? $current : $this->defaultLocale;
        }

        if ($timezone === null) {
            $timezone = $this->config->get('general/timezone');
        }

        if ($timezone !== null) {
            $dateTime->setTimezone(new CarbonTimeZone($timezone));
        }

        $dateTime->locale($locale);

        return $dateTime->translatedFormat($format);
    }
}
