<?php

namespace App\Command;

use App\Entity\Folder;
use App\Entity\ShareLink;
use App\Entity\StoredFile;
use App\Files\FileStorage;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

/** Demo files: a "Projets" folder for Alice, with a note and a shared price list. */
final class FilesDemoSeeder implements DemoSeederInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FolderRepository $folders,
        private readonly FileStorage $storage,
    ) {
    }

    public function seed(array $users, SymfonyStyle $io): void
    {
        $alice = $users['alice@example.org'] ?? null;
        if (null === $alice || null !== $this->folders->findOneBy(['owner' => $alice, 'name' => 'Projets'])) {
            return;
        }
        $projects = (new Folder())->setName('Projets')->setOwner($alice);
        $this->em->persist($projects);

        $files = [
            ['Note de lancement.txt', 'text/plain', "Projet Rocket\n=============\n\nObjectif : une couche Middleware commune (authentification, emails, fichiers).\n"],
            ['Tarifs 2026.csv', 'text/csv', "Produit;Prix HT\nAbonnement Rocket;120\nSupport;40\n"],
        ];
        $price = null;
        foreach ($files as [$name, $mime, $content]) {
            $tmp = (new Filesystem())->tempnam(sys_get_temp_dir(), 'demo');
            file_put_contents($tmp, $content);
            $file = (new StoredFile($alice, $name, \strlen($content), $mime, hash('sha256', $content)))->setFolder($projects);
            $this->storage->store($file, new File($tmp));
            $this->em->persist($file);
            $price = $file;
        }
        $this->em->persist((new ShareLink())->setFile($price)->setOwner($alice)->setMessage('Voici nos tarifs, comme convenu.'));
        $this->em->flush();
        $io->text('Fichiers de démo : dossier « Projets » d’Alice, avec un lien de partage.');
    }
}
