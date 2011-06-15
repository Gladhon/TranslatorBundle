<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Knplabs\Bundle\TranslatorBundle\Translation;

use Symfony\Bundle\FrameworkBundle\Translation\Translator as BaseTranslator;
use Symfony\Component\Config\Resource\ResourceInterface;
use Knplabs\Bundle\TranslatorBundle\Dumper\DumperInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Knplabs\Bundle\TranslatorBundle\Exception\InvalidTranslationKeyException;

/**
 * Translator that adds write capabilites on translation files
 *
 * @author Florian Klein <florian.klein@free.fr>
 *
 */
class Translator extends BaseTranslator
{
    private $dumpers = array();
    private $locales;

    public function getAll($locale)
    {
        return $this->getCatalog($locale)->all();
    }

    /**
     * Adds a dumper to the ones used to dump a resource
     */
    public function addDumper(DumperInterface $dumper)
    {
        $this->dumpers[] = $dumper;
    }

    public function addLocale($locale)
    {
        $this->locales[$locale] = $locale;
    }

    public function getLocales()
    {
        return $this->locales;
    }

    /**
     *
     * @return DumperInterface
     */
    private function getDumper(ResourceInterface $resource)
    {
        foreach ($this->dumpers as $dumper) {
            if ($dumper->supports($resource)) {
                return $dumper;
            }
        }

        return null;
    }

    /**
     *
     * Gets a catalog for a given locale
     *
     * @return MessageCatalogue
     */
    public function getCatalog($locale)
    {
        $this->loadCatalogue($locale);

        if (isset($this->catalogues[$locale])) {

            return $this->catalogues[$locale];
        }

        throw new \InvalidArgumentException(
            sprintf('The locale "%s" does not exist in Translations catalogues', $locale)
        );
    }

    /**
     * Updates the value of a given trans id for a specified domain and locale
     *
     * @param string $id the trans id
     * @param string $value the translated value
     * @param string domain the domain name
     * @param string $locale
     *
     * @return boolean true if success
     */
    public function update($id, $value, $domain, $locale)
    {
        $catalog = $this->getCatalog($locale);

        $resources = $this->getMatchedResources($catalog, $domain, $locale);

        $success = false;
        foreach ($resources as $resource) {
            if ($dumper = $this->getDumper($resource)) {
                // @TODO finally, should we throw an exception ?
                try {
                    $success = $dumper->update($resource, $id, $value);
                }
                catch (InvalidTranslationKeyException $e) {
                    $success = false;
                }
            }
        }

        $this->loadCatalogue($locale);

        return $success;
    }

    protected function loadCatalogue($locale)
    {
        unset($this->catalogues[$locale]);

        parent::loadCatalogue($locale);
    }

    /**
     * Gets the resources that matches a domain and a locale on a particular catalog
     *
     * @param MessageCatalogue $catalog the catalog
     * @param string $domain the domain name (default is 'messages')
     * @param string $locae the locale, to filter fallbackLocale
     * @return array of FileResource objects
     */
    private function getMatchedResources(MessageCatalogue $catalog, $domain, $locale)
    {
        $matched = array();
        foreach ($catalog->getResources() as $resource) {

            // @see Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
            // filename is domain.locale.format
            $basename = \basename($resource->getResource());
            list($resourceDomain, $resourceLocale, $format) = explode('.', $basename);

            if ($domain === $resourceDomain && $locale === $resourceLocale) {
                $matched[] = $resource;
            }
        }

        return $matched;
    }
}
