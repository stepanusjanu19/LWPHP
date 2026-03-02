<?php

namespace Kei\Lwphp\Service;

use Kei\Lwphp\Entity\HeroSetting;
use Kei\Lwphp\Repository\HeroSettingRepository;

class HeroSettingService
{
    private HeroSettingRepository $repository;

    public function __construct(HeroSettingRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getSettings(): HeroSetting
    {
        $setting = $this->repository->getSetting();

        // Seed a default setting if none exists
        if (!$setting) {
            $setting = new HeroSetting();
            $setting->setHeadline('Lightweight PHP Framework');
            $setting->setSubheadline('Elegant design, simple architecture, and blazing fast performance. The next generation of PHP development starts here.');
            $setting->setButtonText('Get Started');
            $setting->setButtonUrl('/admin');
            $this->repository->save($setting);
        }

        return $setting;
    }

    public function updateSettings(array $data): void
    {
        $setting = $this->getSettings();

        if (isset($data['headline'])) {
            $setting->setHeadline(trim($data['headline']));
        }
        if (isset($data['subheadline'])) {
            $setting->setSubheadline(trim($data['subheadline']));
        }
        if (isset($data['buttonText'])) {
            $setting->setButtonText(trim($data['buttonText']));
        }
        if (isset($data['buttonUrl'])) {
            $setting->setButtonUrl(trim($data['buttonUrl']));
        }

        $this->repository->save($setting);
    }
}
