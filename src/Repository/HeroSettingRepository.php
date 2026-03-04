<?php

namespace Kei\Lwphp\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Kei\Lwphp\Entity\HeroSetting;

class HeroSettingRepository
{
    private EntityManagerInterface $em;
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->repository = $em->getRepository(HeroSetting::class);
    }

    public function getSetting(): ?HeroSetting
    {
        // We only expect one active Hero Setting record
        return $this->repository->findOneBy([]);
    }

    public function save(HeroSetting $setting): void
    {
        $this->em->persist($setting);
        $this->em->flush();
    }
}
