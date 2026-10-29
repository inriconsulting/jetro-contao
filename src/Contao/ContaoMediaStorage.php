<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Contao;

use Contao\Automator;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\Folder;
use JetroTools\ContaoBundle\Storage\MediaStorageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ContaoMediaStorage implements MediaStorageInterface
{
    public function __construct(
        private readonly ContaoFramework $framework,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%contao.upload_path%')]
        private readonly string $uploadPath,
    ) {
    }

    public function store(string $id, string $extension, string $content): string
    {
        $this->framework->initialize();

        $folder = new Folder($this->uploadPath.'/jetro');

        // Bilder müssen für Lightbox und Großansicht öffentlich erreichbar sein
        if (!$folder->isUnprotected()) {
            $folder->unprotect();
            (new Automator())->generateSymlinks();
        }

        $path = $this->uploadPath.'/jetro/'.$id.'.'.$extension;

        if (false === file_put_contents($this->projectDir.'/'.$path, $content)) {
            throw new \RuntimeException('Datei konnte nicht geschrieben werden.');
        }

        $model = Dbafs::addResource($path);

        return (string) $model->uuid;
    }

    public function exists(string $uuid): bool
    {
        $this->framework->initialize();

        $model = $this->framework->getAdapter(FilesModel::class)->findByUuid($uuid);

        return null !== $model && is_file($this->projectDir.'/'.$model->path);
    }
}
