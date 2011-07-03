<?php

namespace Knplabs\Bundle\TranslatorBundle\Dumper;

use Knplabs\Bundle\TranslatorBundle\Dumper\DumperInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Yaml\Yaml;
use Knplabs\Bundle\TranslatorBundle\Exception\InvalidTranslationKeyException;

class CsvDumper implements DumperInterface
{
    public function supports(FileResource $resource)
    {
        return 'csv' === pathinfo($resource->getResource(), PATHINFO_EXTENSION);
    }

    /**
     *
     * Updates the content of a csv file with value for the matched trans id
     */
    public function update(FileResource $resource, $id, $value)
    {
        if ('' === $id) {
            throw new InvalidTranslationKeyException(
                sprintf('An empty key can not be used in "%s"', $resource->getResource())
            );
        }

        $lines = $this->all($resource);

        if(false === $fd = fopen($resource->getResource(), 'r+b')) {
            throw new \InvalidArgumentException(sprintf('Error opening file "%s" for writing.', $resource->getResource()));
        }
        // empty the file
        ftruncate($fd, 0);

        $updated = false;
        foreach ($lines as $data) {
            if(0 === strpos($data[0], '#')) {
                continue;
            }
            if ($id === $data[0]) {
                // this line is the one we want to update
                $data[1] = $value;
                $updated = true;
            }
            fputcsv($fd, $data, ';', '"');
        }

        if (false === $updated) {
            $updated = false !== fputcsv($fd, array($id, $value), ';', '"');
        }
        fclose($fd);

        return $updated;
    }

    /**
     *
     * @return array
     */
    private function all($resource)
    {
        try {
            $file = new \SplFileObject($resource->getResource(), 'rb');
        } catch(\RuntimeException $e) {
            throw new \InvalidArgumentException(sprintf('Error opening file "%s".', $resource->getResource()));
        }

        $file->setFlags(\SplFileObject::SKIP_EMPTY | \SplFileObject::READ_CSV);
        $file->setCsvControl(';');

        $lines = array();
        // iterate over the file's rows
        // fgets increments file descriptor to next line
        while($data = $file->fgetcsv()) {
            $lines[] = $data;
        }

        return $lines;
    }
}
